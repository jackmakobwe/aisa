<?php

namespace App\Services\Safety;

use App\Models\NotificationDelivery;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alerts responders that a conversation needs safety review.
 *
 * Alerts contain the ticket reference and level only, never message
 * content, because mail travels through third parties. Every attempt is
 * recorded so a failed alert is visible in the dashboard.
 */
class SafetyNotifier
{
    public function notify(Ticket $ticket, SafetyFlag $flag): void
    {
        foreach ($this->recipients() as $email) {
            try {
                Mail::raw($this->body($ticket, $flag), function ($mail) use ($email, $flag) {
                    $mail->to($email)->subject('Aisa: a conversation needs safety review ('.$flag->level.')');
                });
                $this->record($ticket, $email, 'sent');
            } catch (\Throwable $e) {
                Log::error('Safety alert failed', ['ticket' => $ticket->reference, 'exception' => $e::class]);
                $this->record($ticket, $email, 'failed', $e::class);
            }
        }
    }

    /** @return list<string> */
    public function recipients(): array
    {
        $configured = config('aisa.safety.alert_emails', []);
        if ($configured !== []) {
            return $configured;
        }

        return User::where('is_active', true)->pluck('email')->all();
    }

    private function body(Ticket $ticket, SafetyFlag $flag): string
    {
        $url = route('admin.conversations.show', $ticket);

        return "A conversation has been flagged for safety review.\n\n"
            ."Reference: {$ticket->reference}\n"
            ."Level: {$flag->level}\n"
            .'Reasons: '.implode(', ', $flag->reasons ?? [])."\n"
            .'Raised: '.$flag->raised_at->toDayDateTimeString()."\n\n"
            ."Open the dashboard to review it: {$url}\n\n"
            .'This alert deliberately contains no message content.';
    }

    private function record(Ticket $ticket, string $email, string $status, ?string $error = null): void
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        NotificationDelivery::create([
            'channel' => 'mail',
            'purpose' => 'safety_alert',
            'recipient_hint' => mb_substr($local, 0, 1).'***@'.$domain,
            'ticket_id' => $ticket->id,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
