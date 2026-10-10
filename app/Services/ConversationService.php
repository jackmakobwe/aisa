<?php

namespace App\Services;

use App\Jobs\GenerateDraftReply;
use App\Models\AiAnalysis;
use App\Models\ConsentRecord;
use App\Models\Message;
use App\Models\Ticket;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ConversationAi;
use App\Services\Safety\SafetyService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The public conversation workflow: starting, messaging, choosing help and
 * issuing codes.
 */
class ConversationService
{
    /** Body marker for the system message that shows crisis resources. */
    public const SAFETY_NOTICE = '::safety-resources::';

    public function __construct(
        private readonly ConversationAi $ai,
        private readonly SafetyService $safety,
        private readonly TicketCredentialService $credentials,
        private readonly LanguageDetector $languages,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Record consent, create the ticket and process the first message.
     *
     * @return array{0: Ticket, 1: TurnReport}
     */
    public function start(string $locale, string $ageGroup, string $text): array
    {
        $ticket = DB::transaction(function () use ($locale, $ageGroup) {
            $consent = ConsentRecord::create([
                'consent_version' => config('aisa.consent_version'),
                'locale' => $locale,
                'age_group' => $ageGroup,
                'accepted' => true,
                'decided_at' => now(),
            ]);

            return Ticket::create([
                'reference' => $this->credentials->newReference(),
                'consent_record_id' => $consent->id,
                'locale' => $locale,
                'is_minor' => $ageGroup === 'minor',
                'status' => Ticket::STATUS_OPEN,
                'last_activity_at' => now(),
            ]);
        });

        if ($ticket->is_minor) {
            $this->safety->flagMinor($ticket);
        }

        return [$ticket, $this->handleUserMessage($ticket, $text)];
    }

    public function handleUserMessage(Ticket $ticket, string $text): TurnReport
    {
        if ($ticket->isBlocked()) {
            throw new RuntimeException('Blocked tickets cannot receive messages.');
        }

        $language = $this->languages->detect($text);
        $userMessage = DB::transaction(function () use ($ticket, $text, $language) {
            $message = $ticket->messages()->create([
                'sender' => Message::SENDER_USER,
                'body' => $text,
                'language' => $language,
            ]);

            $updates = [
                'last_user_message_at' => now(),
                'last_activity_at' => now(),
                'language' => $ticket->language ?? $language,
            ];
            // A follow-up after Aisa answered goes back into her queue.
            if ($ticket->wantsHuman() && $ticket->status !== Ticket::STATUS_AWAITING_HUMAN) {
                $updates['status'] = Ticket::STATUS_AWAITING_HUMAN;
            }
            $ticket->forceFill($updates)->save();

            return $message;
        });

        $new = [];
        $safetyShown = false;

        // Deterministic rules run first and regardless of the AI.
        if ($this->safety->apply($ticket, $userMessage, $this->safety->assessText($text))) {
            $new[] = $this->addSafetyNotice($ticket);
            $safetyShown = true;
        }

        // Once someone has asked for Aisa, the AI steps back so it is
        // always clear who is replying.
        if ($ticket->wantsHuman()) {
            return new TurnReport($userMessage, $new, null, $safetyShown);
        }

        return $this->runAiTurn($ticket, $userMessage, $new, $safetyShown);
    }

    /** Retry the AI reply for the latest user message after a failure. */
    public function retry(Ticket $ticket): ?TurnReport
    {
        $last = $ticket->messages()->reorder()->latest('id')->first();
        if (! $last || $last->sender !== Message::SENDER_USER || $ticket->wantsHuman()) {
            return null;
        }

        return $this->runAiTurn($ticket, $last, [], false);
    }

    /**
     * @param  list<Message>  $new
     */
    private function runAiTurn(Ticket $ticket, Message $userMessage, array $new, bool $safetyShown): TurnReport
    {
        try {
            $outcome = $this->ai->turn($ticket);
        } catch (AiUnavailableException $e) {
            $this->ai->recordFailure($ticket, $userMessage, AiAnalysis::KIND_TURN, $e->reason);

            return new TurnReport($userMessage, $new, $e->reason, $safetyShown, true);
        }

        $aiMessage = DB::transaction(function () use ($ticket, $userMessage, $outcome) {
            $message = $ticket->messages()->create([
                'sender' => Message::SENDER_AI,
                'body' => $outcome->reply,
                'language' => $outcome->language,
                'is_mock' => $outcome->isMock,
            ]);

            AiAnalysis::create([
                'ticket_id' => $ticket->id,
                'message_id' => $userMessage->id,
                'kind' => AiAnalysis::KIND_TURN,
                'category' => $outcome->category,
                'urgency' => $outcome->urgency,
                'risk_flag' => $outcome->risk->flagged,
                'risk_types' => $outcome->risk->types,
                'detected_language' => $outcome->language,
                'summary' => $outcome->summary,
                'provider' => $this->ai->provider()->name(),
                'model' => $outcome->result->model,
                'prompt_version' => config('aisa.ai.prompt_version'),
                'input_tokens' => $outcome->result->inputTokens,
                'output_tokens' => $outcome->result->outputTokens,
                'latency_ms' => $outcome->result->latencyMs,
            ]);

            $ticket->forceFill([
                'category' => $outcome->category,
                // Never lower an urgency the safety layer has already raised.
                'urgency' => $this->higherUrgency($ticket->urgency, $outcome->urgency),
                'language' => $outcome->language ?? $ticket->language,
                'last_activity_at' => now(),
            ])->save();

            return $message;
        });

        $new[] = $aiMessage;

        if ($this->safety->apply($ticket, $userMessage, $outcome->risk) && ! $safetyShown) {
            $new[] = $this->addSafetyNotice($ticket);
            $safetyShown = true;
        }

        return new TurnReport($userMessage, $new, null, $safetyShown, $outcome->suggestHuman);
    }

    /**
     * Record the person's choice of help. Returns false when human requests
     * are paused for capacity.
     */
    public function chooseHelp(Ticket $ticket, string $mode): bool
    {
        if ($mode === 'human') {
            if (! $this->settings->humanRequestsOpen() && ! $ticket->safety_flagged) {
                return false;
            }

            $ticket->forceFill([
                'help_mode' => 'human',
                'status' => Ticket::STATUS_AWAITING_HUMAN,
                'human_requested_at' => $ticket->human_requested_at ?? now(),
                'last_activity_at' => now(),
            ])->save();

            // In a web request the draft is prepared after the response is sent,
            // so the person is not kept waiting; elsewhere it runs immediately.
            app()->runningInConsole()
                ? GenerateDraftReply::dispatchSync($ticket->id)
                : GenerateDraftReply::dispatch($ticket->id)->afterResponse();

            return true;
        }

        $ticket->forceFill(['help_mode' => 'ai', 'last_activity_at' => now()])->save();

        return true;
    }

    /** Issue the ticket's code if it has none. Returns the plaintext or null. */
    public function issueCodeIfMissing(Ticket $ticket): ?string
    {
        return $ticket->hasCode() ? null : $this->credentials->issue($ticket);
    }

    public function generateDraft(Ticket $ticket): ?AiAnalysis
    {
        try {
            $outcome = $this->ai->draft($ticket);
        } catch (AiUnavailableException $e) {
            $this->ai->recordFailure($ticket, null, AiAnalysis::KIND_DRAFT, $e->reason);

            return null;
        }

        return AiAnalysis::create([
            'ticket_id' => $ticket->id,
            'kind' => AiAnalysis::KIND_DRAFT,
            'risk_flag' => $outcome->sensitive,
            'summary' => $outcome->notes,
            'draft_reply' => $outcome->draft,
            'provider' => $this->ai->provider()->name(),
            'model' => $outcome->result->model,
            'prompt_version' => config('aisa.ai.prompt_version'),
            'input_tokens' => $outcome->result->inputTokens,
            'output_tokens' => $outcome->result->outputTokens,
            'latency_ms' => $outcome->result->latencyMs,
        ]);
    }

    private function addSafetyNotice(Ticket $ticket): Message
    {
        return $ticket->messages()->create([
            'sender' => Message::SENDER_SYSTEM,
            'body' => self::SAFETY_NOTICE,
        ]);
    }

    private function higherUrgency(?string $current, string $proposed): string
    {
        $order = array_flip(config('aisa.urgency_levels'));

        return $current !== null && ($order[$current] ?? 0) > ($order[$proposed] ?? 0) ? $current : $proposed;
    }
}
