<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sends a reply from Aisa (or a backup responder). The only path by which a
 * message labelled as from a human reaches a user, and it always requires
 * an explicit approval by an authenticated responder.
 */
class HumanReplyService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function send(Ticket $ticket, User $responder, string $body, bool $approved, ?int $draftId = null): Message
    {
        if (! $approved) {
            throw new \InvalidArgumentException('A human reply must be explicitly approved before it is sent.');
        }

        return DB::transaction(function () use ($ticket, $responder, $body, $draftId) {
            $message = $ticket->messages()->create([
                'sender' => Message::SENDER_HUMAN,
                'body' => $body,
                'author_id' => $responder->id,
                'approved_at' => now(),
            ]);

            $ticket->forceFill([
                'status' => Ticket::STATUS_ANSWERED,
                'first_human_reply_at' => $ticket->first_human_reply_at ?? now(),
                'last_human_reply_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            $this->audit->log('reply.sent', $ticket, array_filter([
                'message_id' => $message->id,
                'based_on_draft' => $draftId,
            ]));

            return $message;
        });
    }
}
