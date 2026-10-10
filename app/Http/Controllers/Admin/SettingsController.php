<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\LaunchReadiness;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function index(LaunchReadiness $readiness): View
    {
        return view('admin.settings', [
            'settings' => $this->settings->all(),
            'approvals' => LaunchReadiness::APPROVALS,
            'checks' => $readiness->checks(),
            'config' => [
                'AI provider' => config('aisa.ai.provider'),
                'AI model' => config('aisa.ai.provider') === 'anthropic' ? config('aisa.ai.model') : 'mock (development only)',
                'Prompt version' => config('aisa.ai.prompt_version'),
                'Assistant name' => config('aisa.assistant_name'),
                'Response window' => config('aisa.response_window_hours').' hours',
                'Retention' => config('aisa.retention_days').' days',
                'Under-18 policy' => config('aisa.minor_policy'),
                'Consent notice version' => config('aisa.consent_version'),
                'Daily AI request cap' => config('aisa.ai.daily_request_cap'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'approvals' => ['array'],
            'approvals.*' => ['in:1'],
            'human_requests_open' => ['nullable', 'boolean'],
            'about_bio_en' => ['nullable', 'string', 'max:2000'],
            'about_bio_sw' => ['nullable', 'string', 'max:2000'],
            'about_review_standard_en' => ['nullable', 'string', 'max:1000'],
            'about_review_standard_sw' => ['nullable', 'string', 'max:1000'],
            'backup_responder_name' => ['nullable', 'string', 'max:120'],
        ]);

        $changes = [];
        $set = function (string $key, mixed $value) use (&$changes, $request) {
            if ($this->settings->get($key) !== $value) {
                $this->settings->set($key, $value, $request->user()->id);
                $changes[$key] = is_bool($value) ? $value : 'updated';
            }
        };

        foreach (array_keys(LaunchReadiness::APPROVALS) as $key) {
            $set($key, isset($data['approvals'][str_replace('.', '_', $key)]));
        }
        $set('human_requests_open', $request->boolean('human_requests_open'));
        foreach (['about.bio_en', 'about.bio_sw', 'about.review_standard_en', 'about.review_standard_sw', 'backup_responder_name'] as $key) {
            $set($key, trim((string) ($data[str_replace('.', '_', $key)] ?? '')));
        }

        if ($changes) {
            $this->audit->log('settings.updated', null, $changes);
        }

        return back()->with('status', $changes ? 'Settings saved.' : 'No changes.');
    }
}
