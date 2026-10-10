<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Models\DeletionRequest;
use App\Models\NotificationDelivery;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Services\LaunchReadiness;
use App\Services\SettingsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(LaunchReadiness $readiness, SettingsService $settings): View
    {
        $responseTimes = Ticket::whereNotNull('first_human_reply_at')
            ->whereNotNull('human_requested_at')
            ->where('first_human_reply_at', '>=', now()->subDays(30))
            ->get(['human_requested_at', 'first_human_reply_at'])
            ->map(fn (Ticket $t) => $t->human_requested_at->diffInMinutes($t->first_human_reply_at));

        $openFlags = SafetyFlag::with('ticket:id,reference,language,category')
            ->where('status', '!=', SafetyFlag::STATUS_RESOLVED)
            ->get()
            ->sortBy([fn ($a, $b) => SafetyFlag::LEVELS[$b->level] <=> SafetyFlag::LEVELS[$a->level], ['raised_at', 'asc']])
            ->values();

        return view('admin.dashboard', [
            'counts' => [
                'new' => Ticket::where('created_at', '>=', now()->subDay())->count(),
                'awaiting' => Ticket::where('status', Ticket::STATUS_AWAITING_HUMAN)->count(),
                'answered' => Ticket::where('status', Ticket::STATUS_ANSWERED)->count(),
                'flagged' => $openFlags->count(),
                'deletions' => DeletionRequest::where('status', DeletionRequest::STATUS_PENDING)->count(),
                'contacts' => ContactSubmission::where('status', 'new')->count(),
            ],
            'avgResponseMinutes' => $responseTimes->isEmpty() ? null : (int) round($responseTimes->avg()),
            'responseSample' => $responseTimes->count(),
            'openFlags' => $openFlags->take(5),
            'waiting' => Ticket::with('latestTurnAnalysis')
                ->where('status', Ticket::STATUS_AWAITING_HUMAN)
                ->orderBy('human_requested_at')
                ->limit(8)
                ->get(),
            'failedAlerts' => NotificationDelivery::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
            'blockers' => $readiness->blockers(),
            'humanOpen' => $settings->humanRequestsOpen(),
            'windowHours' => config('aisa.response_window_hours'),
        ]);
    }
}
