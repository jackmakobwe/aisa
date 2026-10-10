@extends('layouts.public')

@section('title', __('ui.get_help_now'))

@section('content')
<div class="container-page py-12">
    <div class="mx-auto max-w-4xl">
        <h1 class="text-4xl">{{ __('help_page.heading') }}</h1>

        <div class="alert-danger mt-6">
            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <div>
                <p class="font-semibold">{{ __('help_page.danger_title') }}</p>
                <p class="mt-1">{{ __('help_page.danger_body') }}</p>
            </div>
        </div>

        <p class="mt-6 text-lg text-ink-soft">{{ __('help_page.not_monitored') }}</p>

        @if ($unverifiedCount > 0 || $resources->isEmpty())
            @unless (app()->isProduction())
                <div class="alert-warning mt-6" role="note">
                    <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
                    <p><strong>{{ __('help_page.admin_notice_title') }}</strong> {{ __('help_page.admin_notice_body', ['count' => $unverifiedCount]) }}</p>
                </div>
            @endunless
        @endif

        <div class="mt-10 space-y-10">
            @foreach ($categories as $category)
                <section aria-labelledby="cat-{{ $category }}">
                    <h2 id="cat-{{ $category }}" class="text-2xl">{{ __("help_page.category_{$category}") }}</h2>
                    <p class="mt-1 text-ink-muted">{{ __("help_page.category_{$category}_hint") }}</p>
                    <div class="mt-4">
                        <x-crisis-resources :resources="$resources->get($category, collect())" />
                    </div>
                </section>
            @endforeach
        </div>

        <div class="card mt-12">
            <h2 class="text-xl">{{ __('help_page.general_title') }}</h2>
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-ink-soft">
                @foreach (__('help_page.general_points') as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
