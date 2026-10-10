<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendReplyRequest;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\SavedResponse;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\ConversationService;
use App\Services\DeletionService;
use App\Services\HumanReplyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'language' => ['nullable', Rule::in(['en', 'sw', 'mixed'])],
            'category' => ['nullable', Rule::in(config('aisa.categories'))],
            'urgency' => ['nullable', Rule::in(config('aisa.urgency_levels'))],
            'status' => ['nullable', Rule::in([...Ticket::STATUSES, 'flagged', 'blocked'])],
            'sort' => ['nullable', Rule::in(['waiting', 'newest', 'urgency', 'activity'])],
            'q' => ['nullable', 'string', 'max:60'],
        ]);

        $query = Ticket::query()->with('latestTurnAnalysis');

        foreach (['language', 'category', 'urgency'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        match ($filters['status'] ?? null) {
            'flagged' => $query->where('safety_flagged', true),
            'blocked' => $query->whereNotNull('blocked_at'),
            null => null,
            default => $query->where('status', $filters['status']),
        };

        if ($q = trim($filters['q'] ?? '')) {
            $query->whereIn('id', $this->search($q));
        }

        match ($filters['sort'] ?? 'waiting') {
            'newest' => $query->latest('id'),
            'activity' => $query->orderByDesc('last_activity_at'),
            'urgency' => $query->orderByRaw("CASE urgency WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")->latest('id'),
            default => $query->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Ticket::STATUS_AWAITING_HUMAN])
                ->orderByDesc('safety_flagged')
                ->orderBy('human_requested_at')
                ->latest('id'),
        };

        if ($q !== '') {
            $this->audit->log('conversation.searched', null, ['matches' => (clone $query)->count()]);
        }

        return view('admin.conversations.index', [
            'tickets' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $this->audit->log('conversation.viewed', $ticket);

        $ticket->load(['messages.author:id,name', 'safetyFlags.reviewer:id,name', 'safetyFlags.assignee:id,name', 'notes.author:id,name', 'deletionRequests']);
        $draft = $ticket->latestDraft;
        $lastHuman = $ticket->messages->where('sender', 'human')->last();

        return view('admin.conversations.show', [
            'ticket' => $ticket,
            'analysis' => $ticket->latestTurnAnalysis,
            // Offer the draft only if it is newer than the last human reply.
            'draft' => $draft && (! $lastHuman || $draft->created_at->gt($lastHuman->created_at)) ? $draft : null,
            'draftFailed' => AiAnalysis::where('ticket_id', $ticket->id)->where('kind', AiAnalysis::KIND_DRAFT)->latest('id')->value('error'),
            'savedResponses' => SavedResponse::orderBy('language')->orderBy('title')->get(),
            'auditTrail' => AuditLog::with('user:id,name')
                ->where('subject_type', 'Ticket')->where('subject_id', $ticket->id)
                ->latest('id')->limit(15)->get(),
        ]);
    }

    public function reply(SendReplyRequest $request, Ticket $ticket, HumanReplyService $replies): RedirectResponse
    {
        if ($ticket->isBlocked()) {
            return back()->with('error', 'This conversation is blocked. Unblock it before replying.');
        }

        $replies->send($ticket, $request->user(), $request->validated('body'), $request->boolean('approved'), $request->validated('draft_id'));

        return redirect()->route('admin.conversations.show', $ticket)->with('status', 'Reply sent. The person will see it when they return with their code.');
    }

    public function regenerateDraft(Ticket $ticket, ConversationService $conversations): RedirectResponse
    {
        $draft = $conversations->generateDraft($ticket);
        $this->audit->log('draft.requested', $ticket, ['ok' => (bool) $draft]);

        return back()->with($draft ? 'status' : 'error', $draft ? 'A new draft is ready below.' : 'The AI could not prepare a draft just now. Please write the reply yourself.');
    }

    public function addNote(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        $note = $ticket->notes()->create(['author_id' => $request->user()->id, 'body' => $data['note']]);
        $this->audit->log('note.added', $ticket, ['note_id' => $note->id]);

        return back()->with('status', 'Private note saved.');
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Ticket::STATUSES)]]);
        $from = $ticket->status;
        $ticket->forceFill(['status' => $data['status']])->save();
        $this->audit->log('conversation.status_changed', $ticket, ['from' => $from, 'to' => $data['status']]);

        return back()->with('status', 'Status updated.');
    }

    public function classify(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(config('aisa.categories'))],
            'urgency' => ['required', Rule::in(config('aisa.urgency_levels'))],
        ]);
        $before = $ticket->only(['category', 'urgency']);
        $ticket->forceFill($data)->save();
        $this->audit->log('conversation.classified', $ticket, ['before' => $before, 'after' => $data]);

        return back()->with('status', 'Category and urgency confirmed.');
    }

    public function block(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['block', 'unblock'])],
            'reason' => ['required_if:action,block', 'nullable', 'string', 'max:200'],
        ]);

        $ticket->forceFill(['blocked_at' => $data['action'] === 'block' ? now() : null])->save();
        $this->audit->log('conversation.'.$data['action'].'ed', $ticket, array_filter(['reason' => $data['reason'] ?? null]));

        return back()->with('status', $data['action'] === 'block' ? 'Code blocked. The person can no longer open this conversation.' : 'Code unblocked.');
    }

    public function destroy(Request $request, Ticket $ticket, DeletionService $deletions): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'confirm_reference' => ['required', Rule::in([$ticket->reference])],
        ], ['confirm_reference.in' => 'Type the reference exactly to confirm.']);

        $deletions->deleteTicket($ticket, $request->user(), $data['reason']);

        return redirect()->route('admin.conversations.index')->with('status', "Conversation {$ticket->reference} was deleted.");
    }

    /**
     * Export one conversation as JSON (for example to answer a data request
     * or a legal duty). Admin only and audited; the file contains personal
     * data and must be handled accordingly.
     */
    public function export(Ticket $ticket): Response
    {
        $this->audit->log('conversation.exported', $ticket);
        $ticket->load(['messages', 'safetyFlags', 'consentRecord']);

        $data = [
            'exported_at' => now()->toIso8601String(),
            'reference' => $ticket->reference,
            'created_at' => $ticket->created_at->toIso8601String(),
            'language' => $ticket->language,
            'status' => $ticket->status,
            'help_mode' => $ticket->help_mode,
            'category' => $ticket->category,
            'urgency' => $ticket->urgency,
            'consent' => $ticket->consentRecord?->only(['consent_version', 'locale', 'age_group', 'decided_at']),
            'messages' => $ticket->messages->map(fn ($m) => [
                'sender' => $m->sender,
                'body' => $m->body,
                'sent_at' => $m->created_at->toIso8601String(),
            ])->all(),
            'safety_flags' => $ticket->safetyFlags->map(fn ($f) => $f->only(['level', 'reasons', 'source', 'status', 'raised_at', 'reviewed_at']))->all(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="aisa-'.$ticket->reference.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Search by reference, or by words in the AI summary. Summaries are
     * encrypted at rest, so they are searched in memory over recent
     * conversations only. That is adequate at pilot scale; see
     * docs/privacy-and-retention.md for the trade-off.
     *
     * @return list<int>
     */
    private function search(string $q): array
    {
        $ids = Ticket::where('reference', 'like', '%'.strtoupper($q).'%')->pluck('id')->all();

        $needle = mb_strtolower($q);
        $recent = AiAnalysis::where('kind', AiAnalysis::KIND_TURN)
            ->whereNotNull('summary')
            ->latest('id')->limit(1000)
            ->get(['id', 'ticket_id', 'summary']);

        foreach ($recent as $analysis) {
            if (str_contains(mb_strtolower((string) $analysis->summary), $needle)) {
                $ids[] = $analysis->ticket_id;
            }
        }

        return array_values(array_unique($ids));
    }
}
