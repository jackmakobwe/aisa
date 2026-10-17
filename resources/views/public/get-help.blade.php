@extends('layouts.public')

@section('title', __('ui.get_help_now'))

@section('content')
<div class="container-page py-10">
    <div class="mx-auto max-w-4xl">
        <h1 class="text-4xl">{{ __('help_page.heading') }}</h1>

        <div class="alert-danger mt-5">
            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <p><strong>{{ __('help_page.danger_title') }}:</strong> {{ __('help_page.danger_short') }}</p>
        </div>

        @if ($preview && $unverifiedCount > 0)
            <p class="alert-warning mt-4 text-sm" role="note">
                <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber"></i>{{ __('help_page.preview_notice', ['count' => $unverifiedCount]) }}
            </p>
        @endif

        {{-- 1. Emergency numbers: one tap to call. --}}
        @if ($hotlines->isNotEmpty())
            <section class="mt-8" aria-labelledby="hotlines-heading">
                <h2 id="hotlines-heading" class="text-2xl">{{ __('help_page.hotlines_title') }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($hotlines as $line)
                        <li>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $line->phone) }}" class="flex h-full items-center gap-4 rounded-2xl border-2 border-clay/30 bg-clay-light/40 p-4 text-ink no-underline transition-colors hover:border-clay">
                                <span class="font-serif text-4xl font-semibold text-clay">{{ $line->phone }}</span>
                                <span class="min-w-0">
                                    <span class="block font-semibold leading-snug">{{ $line->name }}</span>
                                    @if ($line->hours)<span class="block text-xs text-ink-muted">{{ $line->hours }}</span>@endif
                                    @unless ($line->is_verified)<span class="badge-amber mt-1">{{ __('help_page.not_verified') }}</span>@endunless
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- 2. Services in your region, as a list (no map needed). --}}
        <section id="services" class="card mt-10 scroll-mt-24" aria-labelledby="services-heading">
            <h2 id="services-heading" class="flex items-center gap-2 text-2xl"><i data-lucide="map-pin" class="h-6 w-6 text-forest"></i>{{ __('help_page.find_title') }}</h2>
            <form method="GET" action="{{ route('get-help') }}#services" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="region" class="field-label">{{ __('help_page.region_label') }}</label>
                    <select id="region" name="region" class="field">
                        <option value="">{{ __('help_page.region_choose') }}</option>
                        @foreach ($regions as $r)
                            <option value="{{ $r }}" @selected($region === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary shrink-0">{{ __('help_page.region_apply') }}</button>
            </form>

            @if ($region)
                @if ($local->isEmpty())
                    <p class="mt-5 text-ink-soft">{{ __('help_page.region_empty', ['region' => $region]) }}</p>
                @else
                    @foreach ($categories as $category)
                        @continue(! $local->has($category))
                        <h3 class="mt-6 text-lg">{{ __("help_page.category_{$category}") }}</h3>
                        <ul class="divide-y divide-line">
                            @foreach ($local->get($category) as $contact)
                                <x-help-contact :contact="$contact" />
                            @endforeach
                        </ul>
                    @endforeach
                @endif
            @endif
        </section>

        {{-- 3. Services that work across Tanzania. --}}
        @if ($national->isNotEmpty())
            <section class="mt-10" aria-labelledby="national-heading">
                <h2 id="national-heading" class="text-2xl">{{ __('help_page.national_title') }}</h2>
                @foreach ($categories as $category)
                    @continue(! $national->has($category))
                    <h3 class="mt-6 text-lg">{{ __("help_page.category_{$category}") }}</h3>
                    <ul class="divide-y divide-line">
                        @foreach ($national->get($category) as $contact)
                            <x-help-contact :contact="$contact" />
                        @endforeach
                    </ul>
                @endforeach
            </section>
        @endif

        @if ($hotlines->isEmpty() && $local->isEmpty() && $national->isEmpty())
            <p class="mt-8 text-ink-soft">{{ __('help_page.no_verified') }}</p>
        @endif

        {{-- 4. Optional map. Google is loaded only after "Show map". --}}
        <details id="map" class="card mt-10 scroll-mt-24" @if ($showMap) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-2 font-semibold">
                <i data-lucide="map" class="h-5 w-5 text-forest"></i>{{ __('help_page.map_title') }}
                <i data-lucide="chevron-right" class="ml-auto h-4 w-4"></i>
            </summary>
            <form method="GET" action="{{ route('get-help') }}#map" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <input type="hidden" name="map" value="1">
                <div>
                    <label for="service" class="field-label">{{ __('help_page.map_service_label') }}</label>
                    <select id="service" name="service" class="field">
                        @foreach ($services as $s)
                            <option value="{{ $s }}" @selected($service === $s)>{{ __("help_page.map_services.{$s}") }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="map-region" class="field-label">{{ __('help_page.map_region_label') }}</label>
                    <select id="map-region" name="region" class="field">
                        <option value="">{{ __('help_page.region_all') }}</option>
                        @foreach ($regions as $r)
                            <option value="{{ $r }}" @selected($region === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">{{ __('help_page.map_show') }}</button>
            </form>
            <p class="mt-2 text-xs text-ink-muted">{{ __('help_page.map_privacy') }}</p>

            @if ($showMap)
                <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-sand">
                    <iframe src="{{ $mapEmbedUrl }}" title="{{ __('help_page.map_frame_title', ['service' => __("help_page.map_services.{$service}"), 'place' => $region ?? 'Tanzania']) }}"
                        class="block h-[26rem] w-full" loading="lazy" referrerpolicy="{{ $mapReferrerPolicy }}" allowfullscreen></iframe>
                </div>
                <a href="{{ $mapLinkUrl }}" rel="noopener noreferrer" target="_blank" class="btn-secondary btn-sm mt-3">
                    <i data-lucide="external-link" class="h-4 w-4"></i>{{ __('help_page.map_open_app') }}
                </a>
            @endif
        </details>
    </div>
</div>
@endsection
