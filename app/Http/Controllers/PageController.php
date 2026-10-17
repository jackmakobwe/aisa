<?php

namespace App\Http\Controllers;

use App\Models\CrisisResource;
use App\Services\ArticleLibrary;
use App\Services\LaunchReadiness;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * ?topic=money preselects a guidance area; &ask=1 also fills in one of
     * its example questions, so the entry points work without JavaScript.
     */
    public function home(Request $request, ArticleLibrary $articles): View
    {
        $module = in_array($request->query('topic'), config('aisa.modules'), true) ? $request->query('topic') : null;
        $example = $module !== null && is_string($request->query('ask'))
            ? (__("home.modules.{$module}.examples")[(int) $request->query('ask')] ?? null)
            : null;

        return view('public.home', [
            'articles' => $articles->latest(config('articles.home_count')),
            'hasAisaPhoto' => file_exists(public_path('images/aisa.jpg')),
            'module' => $module,
            'example' => is_string($example) ? $example : null,
        ]);
    }

    /** Health education for young mothers and the people supporting them. */
    public function mamaCare(): View
    {
        // Only photos whose files are present; the page works without any.
        $photos = collect(config('mama.photos'))
            ->filter(fn ($photo, $key) => file_exists(public_path("images/mama/{$key}-640.webp")) && file_exists(public_path("images/mama/{$key}-1200.webp")))
            ->all();

        return view('public.mama-care', [
            'reviewed' => (bool) config('aisa.mama_care_reviewed'),
            'photos' => $photos,
        ]);
    }

    public function howItWorks(): View
    {
        return view('public.how-it-works');
    }

    public function about(SettingsService $settings): View
    {
        $locale = app()->getLocale();

        return view('public.about', [
            'bio' => $settings->get("about.bio_{$locale}") ?: $settings->get('about.bio_en'),
            'reviewStandard' => $settings->get("about.review_standard_{$locale}") ?: $settings->get('about.review_standard_en'),
        ]);
    }

    public function getHelp(): View
    {
        $resources = CrisisResource::verified()
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy('category');

        return view('public.get-help', [
            'resources' => $resources,
            'categories' => CrisisResource::CATEGORIES,
            'unverifiedCount' => app()->isProduction() ? 0 : CrisisResource::where('is_verified', false)->count(),
        ]);
    }

    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    public function terms(): View
    {
        return $this->legal('terms');
    }

    /** Draft legal pages, rendered from the language files for both languages. */
    private function legal(string $page): View
    {
        $replace = [
            'days' => config('aisa.retention_days'),
            'hours' => config('aisa.response_window_hours'),
            'name' => config('aisa.assistant_name'),
        ];

        return view('public.legal', [
            'title' => __("legal.{$page}_title"),
            'intro' => __("legal.{$page}_intro", $replace),
            'sections' => __("legal.{$page}_sections", $replace),
        ]);
    }

    public function notOpen(LaunchReadiness $readiness): View
    {
        return view('public.not-open', ['ready' => $readiness->isReady()]);
    }

    public function underAge(): View
    {
        return view('public.under-18');
    }
}
