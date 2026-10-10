<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Totals computed from stored data only. Nothing here is estimated or
 * invented; a chart with no data says so.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days - 1)->startOfDay();
        $tz = config('aisa.display_timezone');

        $tickets = Ticket::where('created_at', '>=', $since)
            ->get(['id', 'created_at', 'category', 'language', 'help_mode', 'human_requested_at', 'first_human_reply_at', 'return_visits', 'rating', 'code_hash']);

        $volume = [];
        for ($d = $since->copy(); $d->lte(now()); $d->addDay()) {
            $volume[$d->copy()->timezone($tz)->format('Y-m-d')] = 0;
        }
        foreach ($tickets as $ticket) {
            $key = $ticket->created_at->timezone($tz)->format('Y-m-d');
            if (isset($volume[$key])) {
                $volume[$key]++;
            }
        }

        $responseHours = $tickets
            ->filter(fn ($t) => $t->human_requested_at && $t->first_human_reply_at)
            ->map(fn ($t) => $t->human_requested_at->diffInMinutes($t->first_human_reply_at) / 60)
            ->sort()->values();

        $window = config('aisa.response_window_hours');
        $withCode = $tickets->filter(fn ($t) => $t->code_hash !== null);
        $ratings = $tickets->pluck('rating')->filter();

        $flags = SafetyFlag::where('raised_at', '>=', $since)->get(['level', 'status', 'raised_at', 'reviewed_at']);
        $reviewMinutes = $flags->filter(fn ($f) => $f->reviewed_at)->map(fn ($f) => $f->raised_at->diffInMinutes($f->reviewed_at));

        return view('admin.analytics', [
            'days' => $days,
            'total' => $tickets->count(),
            'volume' => $volume,
            'categories' => $this->counts($tickets->pluck('category'), config('aisa.categories')),
            'languages' => $this->counts($tickets->pluck('language'), ['en', 'sw', 'mixed']),
            'humanRequests' => $tickets->where('help_mode', 'human')->count(),
            'responseCount' => $responseHours->count(),
            'responseMedian' => $responseHours->isEmpty() ? null : round($responseHours->median(), 1),
            'withinWindow' => $responseHours->isEmpty() ? null : (int) round(100 * $responseHours->filter(fn ($h) => $h <= $window)->count() / $responseHours->count()),
            'window' => $window,
            'codesIssued' => $withCode->count(),
            'returned' => $withCode->filter(fn ($t) => $t->return_visits > 0)->count(),
            'ratingAvg' => $ratings->isEmpty() ? null : round($ratings->avg(), 1),
            'ratingCount' => $ratings->count(),
            'flagLevels' => $this->counts($flags->pluck('level'), array_keys(SafetyFlag::LEVELS)),
            'flagsOpen' => $flags->where('status', '!=', SafetyFlag::STATUS_RESOLVED)->count(),
            'flagReviewMedian' => $reviewMinutes->isEmpty() ? null : (int) $reviewMinutes->median(),
        ]);
    }

    /** @return array<string, int> */
    private function counts($values, array $keys): array
    {
        $counts = array_fill_keys($keys, 0);
        foreach ($values as $value) {
            if ($value !== null && isset($counts[$value])) {
                $counts[$value]++;
            }
        }

        return $counts;
    }
}
