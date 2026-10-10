<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\Safety\SafetyAssessment;
use App\Services\Safety\SafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SafetyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $open = SafetyFlag::with(['ticket:id,reference,language,category,status,help_mode,code_hash,is_minor', 'assignee:id,name'])
            ->where('status', '!=', SafetyFlag::STATUS_RESOLVED)
            ->get()
            ->sortBy([fn ($a, $b) => SafetyFlag::LEVELS[$b->level] <=> SafetyFlag::LEVELS[$a->level], ['raised_at', 'asc']])
            ->values();

        $resolved = SafetyFlag::with(['ticket:id,reference', 'reviewer:id,name'])
            ->where('status', SafetyFlag::STATUS_RESOLVED)
            ->latest('reviewed_at')->limit(20)->get();

        return view('admin.safety.index', compact('open', 'resolved'));
    }

    public function assign(Request $request, SafetyFlag $flag): RedirectResponse
    {
        abort_unless($flag->isOpen(), 422, 'This flag is already resolved.');

        $flag->forceFill(['assigned_to' => $request->user()->id, 'status' => SafetyFlag::STATUS_IN_REVIEW])->save();
        $this->audit->log('safety.assigned', $flag, ['ticket_id' => $flag->ticket_id]);

        return back()->with('status', 'You are now reviewing this flag.');
    }

    /**
     * Resolution always needs a person, notes and an explicit confirmation
     * that the full conversation was reviewed.
     */
    public function resolve(Request $request, SafetyFlag $flag): RedirectResponse
    {
        abort_unless($flag->isOpen(), 422, 'This flag is already resolved.');

        $data = $request->validate([
            'outcome' => ['required', 'string', 'min:10', 'max:2000'],
            'reviewed_full' => ['accepted'],
        ], [
            'reviewed_full.accepted' => 'Confirm that you have reviewed the full conversation.',
            'outcome.min' => 'Describe what you did and why, in at least a sentence.',
        ]);

        $flag->forceFill([
            'status' => SafetyFlag::STATUS_RESOLVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'outcome' => $data['outcome'],
        ])->save();

        $ticket = $flag->ticket;
        if (! $ticket->safetyFlags()->where('status', '!=', SafetyFlag::STATUS_RESOLVED)->exists()) {
            $ticket->forceFill(['safety_flagged' => false])->save();
        }

        $this->audit->log('safety.resolved', $flag, ['ticket_id' => $flag->ticket_id, 'minutes_open' => (int) $flag->raised_at->diffInMinutes(now())]);

        return back()->with('status', 'Flag resolved and recorded.');
    }

    /** Raise a flag by hand when a responder spots something the system did not. */
    public function flag(Request $request, Ticket $ticket, SafetyService $safety): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(SafetyAssessment::TYPES)],
        ]);

        $flag = $safety->apply($ticket, null, new SafetyAssessment(true, SafetyAssessment::levelFor($data['types']), $data['types'], 'manual'));
        $this->audit->log('safety.flagged_manually', $ticket, ['types' => $data['types']]);

        return back()->with('status', $flag ? 'Safety flag raised.' : 'This conversation already has an open flag at that level; the reasons were added.');
    }
}
