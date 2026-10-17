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

    /**
     * Emergency hotlines first, then a list of every service in the chosen
     * region (for people who do not use maps), then national services. The
     * map is embedded only when the person asks for it (?map=1), so Google is
     * not contacted on an ordinary visit.
     *
     * Only verified contacts are public. On a developer's own machine
     * (APP_ENV=local) unverified ones are shown too, clearly labelled, so
     * the page can be checked before the contacts are.
     */
    public function getHelp(Request $request): View
    {
        $regions = config('help.regions');
        $region = in_array($request->query('region'), $regions, true) ? $request->query('region') : null;
        $preview = app()->isLocal();

        $contacts = CrisisResource::query()
            ->when(! $preview, fn ($q) => $q->verified())
            ->forRegion($region)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        [$national, $local] = $contacts->partition(fn (CrisisResource $c) => $c->region === null);
        [$hotlines, $nationalServices] = $national->partition(fn (CrisisResource $c) => filled($c->phone) && in_array($c->category, ['emergency', 'child', 'health', 'mental_health'], true) && strlen(preg_replace('/\D/', '', $c->phone)) <= 4);

        $services = config('help.map_services');
        $service = array_key_exists((string) $request->query('service'), $services) ? $request->query('service') : 'police';
        $mapQuery = $services[$service].' in '.($region ?? 'Tanzania').($region ? ', Tanzania' : '');

        return view('public.get-help', [
            // Police, ambulance and fire first, then helplines.
            'hotlines' => $hotlines->sortBy(fn (CrisisResource $c) => [array_search($c->category, CrisisResource::CATEGORIES, true), $c->sort_order])->values(),
            'local' => $local->groupBy('category'),
            'national' => $nationalServices->groupBy('category'),
            'categories' => CrisisResource::CATEGORIES,
            'preview' => $preview,
            'regions' => $regions,
            'region' => $region,
            'services' => array_keys($services),
            'service' => $service,
            'showMap' => $request->boolean('map'),
            'mapEmbedUrl' => $this->mapEmbedUrl($mapQuery),
            // A key restricted to this domain needs the site origin (never the path).
            'mapReferrerPolicy' => config('help.maps_embed_key') ? 'strict-origin' : 'no-referrer',
            'mapLinkUrl' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($mapQuery),
            'unverifiedCount' => app()->isProduction() ? 0 : CrisisResource::where('is_verified', false)->count(),
        ]);
    }

    private function mapEmbedUrl(string $query): string
    {
        $key = config('help.maps_embed_key');

        return $key
            ? 'https://www.google.com/maps/embed/v1/search?key='.rawurlencode($key).'&q='.rawurlencode($query)
            : 'https://maps.google.com/maps?q='.rawurlencode($query).'&output=embed';
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
