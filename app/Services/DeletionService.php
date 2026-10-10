<?php

namespace App\Services;

use App\Models\DeletionRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * User deletion requests and automatic retention.
 *
 * Deleting a ticket removes its messages, AI analyses, notes and safety
 * flags through foreign-key cascades. The audit log keeps only the ticket
 * reference and who processed it. Whether safety records must be kept
 * longer is a legal question (project document section 10); until that is
 * answered, they are deleted with the conversation.
 */
class DeletionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function request(Ticket $ticket): DeletionRequest
    {
        return $ticket->deletionRequests()->firstOrCreate(
            ['status' => DeletionRequest::STATUS_PENDING],
            ['ticket_reference' => $ticket->reference, 'requested_at' => now()],
        );
    }

    public function complete(DeletionRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user) {
            $reference = $request->ticket_reference;
            $request->ticket?->delete();

            $request->forceFill([
                'status' => DeletionRequest::STATUS_COMPLETED,
                'processed_by' => $user->id,
                'completed_at' => now(),
            ])->save();

            $this->audit->log('deletion.completed', $request, ['ticket_reference' => $reference]);
        });
    }

    public function reject(DeletionRequest $request, User $user, string $note): void
    {
        $request->forceFill([
            'status' => DeletionRequest::STATUS_REJECTED,
            'processed_by' => $user->id,
            'completed_at' => now(),
            'note' => $note,
        ])->save();

        $this->audit->log('deletion.rejected', $request, ['ticket_reference' => $request->ticket_reference]);
    }

    /** Delete a ticket directly (staff action, e.g. abusive content). */
    public function deleteTicket(Ticket $ticket, User $user, string $reason): void
    {
        DB::transaction(function () use ($ticket, $user, $reason) {
            $reference = $ticket->reference;
            $ticket->delete();
            $this->audit->log('ticket.deleted', null, ['ticket_reference' => $reference, 'reason' => $reason], $user->id);
        });
    }

    /**
     * Remove conversations inactive for longer than the retention period.
     * Conversations with an unresolved safety flag are kept until a person
     * resolves the flag.
     */
    public function pruneExpired(): int
    {
        $cutoff = now()->subDays(config('aisa.retention_days'));
        $count = 0;

        Ticket::where('last_activity_at', '<', $cutoff)
            ->whereDoesntHave('safetyFlags', fn ($q) => $q->where('status', '!=', 'resolved'))
            ->chunkById(200, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $ticket->delete();
                    $count++;
                }
            });

        if ($count > 0) {
            $this->audit->log('retention.pruned', null, ['tickets' => $count, 'retention_days' => config('aisa.retention_days')]);
        }

        return $count;
    }
}
