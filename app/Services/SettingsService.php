<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime settings editable from the admin dashboard. Launch approvals live
 * here so a person, not a deploy, records that a review has happened.
 */
class SettingsService
{
    public const DEFAULTS = [
        // Launch approvals (project document, Gate A). All false until a
        // responsible person records the review in the dashboard.
        'approval.legal_review' => false,
        'approval.minor_policy' => false,
        'approval.kiswahili_review' => false,
        'approval.safety_tests' => false,
        'approval.backup_responder' => false,
        'approval.assistant_name' => false,

        // Capacity: when false, new "Talk to Aisa" requests are paused and
        // users are told so, rather than replies being silently missed.
        'human_requests_open' => true,

        // About page content. Left empty until Aisa approves it; nothing is
        // invented about the responder.
        'about.bio_en' => '',
        'about.bio_sw' => '',
        'about.review_standard_en' => '',
        'about.review_standard_sw' => '',
        'backup_responder_name' => '',
    ];

    private const CACHE_KEY = 'aisa.settings';

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public function all(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, fn () => Setting::pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        Cache::forget(self::CACHE_KEY);
    }

    public function humanRequestsOpen(): bool
    {
        return (bool) $this->get('human_requests_open');
    }
}
