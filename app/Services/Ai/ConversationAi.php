<?php

namespace App\Services\Ai;

use App\Models\AiAnalysis;
use App\Models\Message;
use App\Models\Ticket;
use App\Services\Safety\SafetyAssessment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Turns ticket history into model requests and model output into validated
 * suggestions. Nothing here sends anything to a user as Aisa.
 */
class ConversationAi
{
    /** How many of the person's latest messages are used to pick reference notes. */
    private const RETRIEVAL_MESSAGES = 3;

    public function __construct(
        private readonly AiProvider $provider,
        private readonly KnowledgeBase $knowledge,
    ) {}

    public function provider(): AiProvider
    {
        return $this->provider;
    }

    /**
     * Generate the AI's next reply to the user.
     *
     * @throws AiUnavailableException
     */
    public function turn(Ticket $ticket): TurnOutcome
    {
        $history = $this->history($ticket);
        if ($history === [] || end($history)['role'] !== 'user') {
            throw new AiUnavailableException('nothing_to_answer');
        }

        $aiReplies = $ticket->messages()->where('sender', Message::SENDER_AI)->count();
        $notes = $this->notesFor($history, $ticket->module);
        $system = Prompts::turnSystem(
            assistantName: config('aisa.assistant_name'),
            repliesSoFar: $aiReplies,
            maxQuestions: config('aisa.ai.max_follow_up_questions'),
            isMinor: $ticket->is_minor,
            locale: $ticket->locale,
            responseWindowHours: config('aisa.response_window_hours'),
            module: $ticket->module,
            notes: $notes,
        );

        $result = $this->call($system, $history, Prompts::turnSchema(), 2000);
        $data = $result->data;

        $reply = trim((string) ($data['reply'] ?? ''));
        if ($reply === '') {
            throw new AiUnavailableException('empty_reply');
        }

        $risk = (array) ($data['risk'] ?? []);
        $modelRisk = ! empty($risk['flag'])
            ? SafetyAssessment::fromTypes(array_map('strval', (array) ($risk['types'] ?? [])), 'model')
            : SafetyAssessment::none('model');
        // The model said "risk" but gave no recognised type: still flag it.
        if (! empty($risk['flag']) && ! $modelRisk->flagged) {
            $modelRisk = new SafetyAssessment(true, 'high', [], 'model');
        }

        return new TurnOutcome(
            reply: Str::limit($reply, 6000, ''),
            language: $this->oneOf($data['language'] ?? null, ['en', 'sw', 'mixed'], null),
            category: $this->oneOf($data['category'] ?? null, config('aisa.categories'), 'other'),
            urgency: $this->oneOf($data['urgency'] ?? null, config('aisa.urgency_levels'), 'medium'),
            risk: $modelRisk,
            summary: Str::limit(trim((string) ($data['summary'] ?? '')), 300),
            suggestHuman: (bool) ($data['suggest_human'] ?? false),
            result: $result,
            isMock: $this->provider->isMock(),
            knowledgeIds: array_map(fn (KnowledgeNote $n) => $n->id, $notes),
        );
    }

    /**
     * Draft a reply for Aisa to review. Never sent automatically.
     *
     * @throws AiUnavailableException
     */
    public function draft(Ticket $ticket): DraftOutcome
    {
        $history = $this->history($ticket, includeHuman: true);
        $notes = $this->notesFor($history, $ticket->module);
        $transcript = collect($history)
            ->map(fn ($m) => ($m['role'] === 'user' ? 'Person' : 'Reply').': '.$m['content'])
            ->implode("\n\n");

        $result = $this->call(
            Prompts::draftSystem($ticket->is_minor, $notes),
            [['role' => 'user', 'content' => "<conversation>\n{$transcript}\n</conversation>\n\nWrite the draft reply for Aisa."]],
            Prompts::draftSchema(),
            3000,
        );

        $draft = trim((string) ($result->data['draft'] ?? ''));
        if ($draft === '') {
            throw new AiUnavailableException('empty_draft');
        }

        return new DraftOutcome(
            draft: $draft,
            sensitive: (bool) ($result->data['sensitive'] ?? true),
            notes: Str::limit(trim((string) ($result->data['notes_for_responder'] ?? '')), 500),
            result: $result,
            knowledgeIds: array_map(fn (KnowledgeNote $n) => $n->id, $notes),
        );
    }

    /**
     * Reference notes matching the person's latest messages.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @return list<KnowledgeNote>
     */
    private function notesFor(array $history, ?string $module): array
    {
        $recent = array_slice(array_values(array_filter($history, fn ($m) => $m['role'] === 'user')), -self::RETRIEVAL_MESSAGES);

        return $recent === [] ? [] : $this->knowledge->search(implode("\n", array_column($recent, 'content')), $module);
    }

    public function recordFailure(Ticket $ticket, ?Message $message, string $kind, string $reason): AiAnalysis
    {
        return AiAnalysis::create([
            'ticket_id' => $ticket->id,
            'message_id' => $message?->id,
            'kind' => $kind,
            'provider' => $this->provider->name(),
            'model' => $this->provider->model(),
            'prompt_version' => config('aisa.ai.prompt_version'),
            'error' => Str::limit($reason, 120, ''),
        ]);
    }

    private function call(string $system, array $messages, array $schema, int $maxTokens): AiResult
    {
        $this->enforceDailyCap();

        return $this->provider->generateJson($system, $messages, $schema, $maxTokens);
    }

    /**
     * Recent conversation as model messages. Names, codes and other
     * identifiers are not part of message bodies and so are never sent.
     *
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(Ticket $ticket, bool $includeHuman = true): array
    {
        // reorder(): the relation sorts oldest-first; take the newest N, then restore chronological order.
        $messages = $ticket->messages()
            ->whereIn('sender', array_filter([Message::SENDER_USER, Message::SENDER_AI, $includeHuman ? Message::SENDER_HUMAN : null]))
            ->reorder()
            ->latest('id')
            ->limit(config('aisa.ai.context_messages'))
            ->get()
            ->reverse();

        $history = [];
        foreach ($messages as $message) {
            $role = $message->sender === Message::SENDER_USER ? 'user' : 'assistant';
            $content = $message->sender === Message::SENDER_HUMAN
                ? '[Earlier personal reply from Aisa, the human responder] '.$message->body
                : $message->body;

            // Mock output is never fed back as if a model had said it.
            if ($message->is_mock) {
                continue;
            }

            $history[] = ['role' => $role, 'content' => $content];
        }

        // The API requires the first message to be from the user.
        while ($history !== [] && $history[0]['role'] !== 'user') {
            array_shift($history);
        }

        return $history;
    }

    private function enforceDailyCap(): void
    {
        $key = 'aisa.ai.requests.'.now()->format('Ymd');
        Cache::add($key, 0, now()->endOfDay());
        if (Cache::increment($key) > config('aisa.ai.daily_request_cap')) {
            throw new AiUnavailableException('daily_cap');
        }
    }

    private function oneOf(mixed $value, array $allowed, ?string $default): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }
}
