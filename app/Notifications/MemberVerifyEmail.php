<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Email confirmation. Deliberately neutral: someone else reading the inbox
 * should not learn what kind of help the person is looking for.
 */
class MemberVerifyEmail extends Notification
{
    public function via(Member $member): array
    {
        return ['mail'];
    }

    public function toMail(Member $member): MailMessage
    {
        $url = URL::temporarySignedRoute('account.verify', now()->addMinutes(60), [
            'id' => $member->getKey(),
            'hash' => sha1($member->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject(__('account.mail_verify_subject'))
            ->greeting(__('account.mail_greeting'))
            ->line(__('account.mail_verify_line'))
            ->action(__('account.mail_verify_button'), $url)
            ->line(__('account.mail_verify_expiry'))
            ->line(__('account.mail_ignore'))
            ->salutation(__('account.mail_salutation'));
    }
}
