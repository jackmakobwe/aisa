@extends('layouts.public')

@section('title', __('ui.nav_about'))

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-4xl">{{ __('about.heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('about.mission') }}</p>

        <section class="card mt-10 flex flex-col gap-6 sm:flex-row" aria-labelledby="about-aisa">
            {{-- Replace with an approved photograph: public/images/aisa.jpg --}}
            @if (file_exists(public_path('images/aisa.jpg')))
                <img src="{{ asset('images/aisa.jpg') }}" alt="{{ __('about.photo_alt') }}" class="h-36 w-36 shrink-0 rounded-2xl object-cover" width="144" height="144" loading="lazy">
            @else
                <div class="flex h-36 w-36 shrink-0 items-center justify-center rounded-2xl bg-sage-light text-forest" role="img" aria-label="{{ __('about.photo_placeholder') }}">
                    <x-logo class="h-16 w-16" />
                </div>
            @endif
            <div>
                <h2 id="about-aisa" class="text-2xl">{{ __('about.who_heading') }}</h2>
                @if ($bio)
                    <p class="mt-2 whitespace-pre-line text-ink-soft">{{ $bio }}</p>
                @else
                    <p class="mt-2 text-ink-soft">{{ __('about.bio_pending') }}</p>
                @endif
            </div>
        </section>

        <section class="mt-10 grid gap-6 sm:grid-cols-2">
            <div class="card">
                <h2 class="text-xl">{{ __('about.is_heading') }}</h2>
                <ul class="mt-3 space-y-2 text-ink-soft">
                    @foreach (__('about.is') as $item)
                        <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="card">
                <h2 class="text-xl">{{ __('about.is_not_heading') }}</h2>
                <ul class="mt-3 space-y-2 text-ink-soft">
                    @foreach (__('about.is_not') as $item)
                        <li class="flex gap-2"><i data-lucide="x" class="mt-1 h-4 w-4 shrink-0 text-clay"></i>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <div class="mt-10">
            <x-content-sections :sections="__('about.sections', ['name' => config('aisa.assistant_name'), 'hours' => config('aisa.response_window_hours')])" />
            @if ($reviewStandard)
                <div class="prose-aisa"><p class="whitespace-pre-line">{{ $reviewStandard }}</p></div>
            @endif
        </div>
    </div>
</div>
@endsection
