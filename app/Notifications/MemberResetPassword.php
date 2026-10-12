<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Password reset link, worded neutrally like MemberVerifyEmail. */
class MemberResetPassword extends Notification
{
    public function __construct(public readonly string $token) {}

    public function via(Member $member): array
    {
        return ['mail'];
    }

    public function toMail(Member $member): MailMessage
    {
        $url = route('account.password.reset', ['token' => $this->token, 'email' => $member->email]);

        return (new MailMessage)
            ->subject(__('account.mail_reset_subject'))
            ->greeting(__('account.mail_greeting'))
            ->line(__('account.mail_reset_line'))
            ->action(__('account.mail_reset_button'), $url)
            ->line(__('account.mail_reset_expiry', ['minutes' => config('auth.passwords.members.expire')]))
            ->line(__('account.mail_ignore'))
            ->salutation(__('account.mail_salutation'));
    }
}
