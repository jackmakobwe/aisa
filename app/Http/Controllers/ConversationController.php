<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserMessageRequest;
use App\Models\CrisisResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Services\ConversationService;
use App\Services\DeletionService;
use App\Services\TicketAccess;
use App\Services\TurnReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function show(Request $request): View
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $messages = $ticket->messages()->get();

        return view('public.conversation', [
            'ticket' => $ticket,
            'messages' => $messages,
            'resources' => $this->resources($messages),
            'hasHumanReply' => $messages->contains('sender', Message::SENDER_HUMAN),
            'canRetry' => $messages->last()?->sender === Message::SENDER_USER && ! $ticket->wantsHuman(),
            'pendingDeletion' => $ticket->hasPendingDeletion(),
        ]);
    }

    public function store(UserMessageRequest $request, ConversationService $conversations): JsonResponse|RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');

        if ($ticket->hasPendingDeletion()) {
            return $this->fail($request, __('chat.deletion_pending'));
        }

        $report = $conversations->handleUserMessage($ticket, $request->validated('message'));

        return $this->respond($request, $ticket, $report, includeUser: true);
    }

    public function retry(Request $request, ConversationService $conversations): JsonResponse|RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $report = $conversations->retry($ticket);

        if (! $report) {
            return $request->expectsJson() ? response()->json(['html' => '']) : redirect()->route('conversation.show');
        }

        return $this->respond($request, $ticket, $report, includeUser: false);
    }

    public function leave(Request $request, TicketAccess $access): RedirectResponse
    {
        $access->revoke();

        return redirect()->route('home')->with('status', __('chat.left'));
    }

    public function rate(Request $request): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5']]);

        if ($ticket->messages()->where('sender', Message::SENDER_HUMAN)->exists()) {
            $ticket->forceFill(['rating' => $data['rating']])->save();
        }

        return redirect()->route('conversation.show')->with('status', __('chat.rating_thanks'));
    }

    public function requestDeletion(Request $request, DeletionService $deletions): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $request->validate(['confirm_delete' => ['accepted']], ['confirm_delete.accepted' => __('chat.delete_confirm_required')]);

        $deletions->request($ticket);

        return redirect()->route('conversation.show')->with('status', __('chat.delete_requested'));
    }

    private function respond(Request $request, Ticket $ticket, TurnReport $report, bool $includeUser): JsonResponse|RedirectResponse
    {
        $error = $report->aiError ? __('chat.ai_unavailable') : null;

        if (! $request->expectsJson()) {
            return redirect()->route('conversation.show')->with($error ? ['ai_error' => true] : []);
        }

        $messages = collect($includeUser ? [$report->userMessage] : [])->merge($report->newMessages);
        $html = $messages->map(fn (Message $m) => view('public.partials.message', [
            'message' => $m,
            'ticket' => $ticket,
            'resources' => $this->resources($messages),
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'error' => $error,
            'can_retry' => $report->aiError !== null,
            'suggest_human' => $report->suggestHuman && ! $ticket->help_mode,
            'safety' => $report->safetyRaised,
        ]);
    }

    private function fail(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 409)
            : redirect()->route('conversation.show')->with('status', $message);
    }

    /** Verified resources, loaded only when a safety notice is shown. */
    private function resources($messages)
    {
        $needed = collect($messages)->contains(fn (Message $m) => $m->sender === Message::SENDER_SYSTEM && $m->body === ConversationService::SAFETY_NOTICE);

        return $needed ? CrisisResource::verified()->orderBy('sort_order')->get() : collect();
    }
}
