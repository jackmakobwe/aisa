<?php

namespace App\Services;

use App\Models\CrisisResource;
use App\Models\User;

/**
 * Release blockers from the project document (Gate A and section 15).
 *
 * In production, the public conversation intake stays closed until every
 * check passes. In development the same list is shown as a banner and in
 * the admin dashboard, so nothing is presented as launch-ready by accident.
 */
class LaunchReadiness
{
    public const APPROVALS = [
        'approval.legal_review' => 'Legal review of privacy policy, terms, data protection, counselling rules and minors (Tanzanian lawyer)',
        'approval.minor_policy' => 'Under-18 policy approved',
        'approval.kiswahili_review' => 'All Kiswahili wording reviewed by a native speaker',
        'approval.safety_tests' => 'Safety test set run against the configured model, misses addressed, review process agreed',
        'approval.backup_responder' => 'Backup responder named and trained',
        'approval.assistant_name' => 'AI assistant name confirmed',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return list<array{key: string, ok: bool, label: string}>
     */
    public function checks(): array
    {
        $checks = [];
        $add = function (string $key, bool $ok, string $label) use (&$checks) {
            $checks[] = compact('key', 'ok', 'label');
        };

        $add('ai.provider', config('aisa.ai.provider') === 'anthropic' && filled(config('aisa.ai.api_key')),
            'A real AI provider is configured (not the development mock)');
        $add('tickets.pepper', filled(config('aisa.tickets.pepper')),
            'AISA_TICKET_PEPPER is set');

        $verified = CrisisResource::verified();
        $add('crisis.emergency', (clone $verified)->where('category', 'emergency')->exists(),
            'At least one verified emergency contact is published on Get help now');
        $add('crisis.categories', (clone $verified)->distinct()->count('category') >= 4,
            'Verified contacts cover at least four help categories');
        $add('crisis.fresh', ! (clone $verified)->get()->contains(fn ($r) => $r->isDueForReview()),
            'All verified contacts were re-checked in the last '.CrisisResource::REVIEW_AFTER_DAYS.' days');

        $add('safety.alerts', config('aisa.safety.alert_emails') !== [] && config('mail.default') !== 'log',
            'Safety alerts go to a real mailbox (AISA_SAFETY_ALERT_EMAILS and a real mailer)');
        $add('admin.two_factor', User::where('is_active', true)->whereNotNull('two_factor_confirmed_at')->exists(),
            'At least one active responder has two-factor authentication set up');

        foreach (self::APPROVALS as $key => $label) {
            $add($key, (bool) $this->settings->get($key), $label);
        }

        if (app()->isProduction()) {
            $add('app.debug', ! config('app.debug'), 'APP_DEBUG is false');
            $add('app.https', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS');
            $add('session.secure', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE is true');
        }

        return $checks;
    }

    /** @return list<array{key: string, ok: bool, label: string}> */
    public function blockers(): array
    {
        return array_values(array_filter($this->checks(), fn ($c) => ! $c['ok']));
    }

    public function isReady(): bool
    {
        return $this->blockers() === [];
    }
}
