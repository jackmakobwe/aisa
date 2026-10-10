<?php

namespace App\Services\Safety;

use App\Models\Message;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Raises and escalates safety flags. Flags are opened or escalated here
 * and only ever resolved by a person in the dashboard.
 */
class SafetyService
{
    public function __construct(
        private readonly RuleBasedSafetyClassifier $rules,
        private readonly SafetyNotifier $notifier,
    ) {}

    public function assessText(string $text): SafetyAssessment
    {
        return $this->rules->assess($text);
    }

    /**
     * Apply an assessment to a ticket. Returns the flag when one was opened
     * or escalated, so the caller can show resources to the user.
     */
    public function apply(Ticket $ticket, ?Message $message, SafetyAssessment $assessment): ?SafetyFlag
    {
        if (! $assessment->flagged) {
            return null;
        }

        [$flag, $isNewOrEscalated] = DB::transaction(function () use ($ticket, $message, $assessment) {
            $open = $ticket->safetyFlags()
                ->where('status', '!=', SafetyFlag::STATUS_RESOLVED)
                ->lockForUpdate()
                ->first();

            if ($open) {
                $reasons = array_values(array_unique(array_merge($open->reasons ?? [], $assessment->types)));
                $escalate = SafetyFlag::LEVELS[$assessment->level] > SafetyFlag::LEVELS[$open->level];
                $open->reasons = $reasons;
                if ($escalate) {
                    $open->level = $assessment->level;
                    // An escalation needs fresh attention.
                    $open->status = SafetyFlag::STATUS_OPEN;
                }
                $open->save();

                return [$open, $escalate];
            }

            $flag = SafetyFlag::create([
                'ticket_id' => $ticket->id,
                'message_id' => $message?->id,
                'level' => $assessment->level,
                'reasons' => $assessment->types,
                'source' => $assessment->source,
                'status' => SafetyFlag::STATUS_OPEN,
                'raised_at' => now(),
            ]);

            return [$flag, true];
        });

        $ticket->forceFill([
            'safety_flagged' => true,
            'urgency' => $assessment->level === 'critical' || $ticket->urgency === 'critical' ? 'critical' : 'high',
        ])->save();

        if ($isNewOrEscalated) {
            $this->notifier->notify($ticket, $flag);
        }

        return $isNewOrEscalated ? $flag : null;
    }

    /**
     * Under-18 users are queued for human review under the "restrict"
     * policy, without being treated as a crisis.
     */
    public function flagMinor(Ticket $ticket): SafetyFlag
    {
        $flag = SafetyFlag::create([
            'ticket_id' => $ticket->id,
            'level' => 'concern',
            'reasons' => ['minor'],
            'source' => 'minor',
            'status' => SafetyFlag::STATUS_OPEN,
            'raised_at' => now(),
        ]);
        $ticket->forceFill(['safety_flagged' => true])->save();

        return $flag;
    }
}
