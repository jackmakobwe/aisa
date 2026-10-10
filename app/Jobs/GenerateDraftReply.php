<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Prepares an AI draft for Aisa after someone asks for a personal reply.
 * The draft is stored for review only and is never sent automatically.
 */
class GenerateDraftReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $ticketId) {}

    public function handle(ConversationService $conversations): void
    {
        $ticket = Ticket::find($this->ticketId);
        if ($ticket && ! $ticket->isBlocked()) {
            $conversations->generateDraft($ticket);
        }
    }
}
