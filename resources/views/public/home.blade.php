@extends('layouts.public')

@section('content')
<section class="border-b border-line bg-gradient-to-b from-sand/70 to-ivory">
    <div class="container-page py-14 sm:py-20">
        <div class="reading">
            <p class="mb-4 text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.eyebrow') }}</p>
            <h1 class="text-4xl leading-tight sm:text-5xl">{{ __('home.heading') }}</h1>
            <p class="mt-4 text-lg text-ink-soft">{{ __('home.subheading') }}</p>

            <form method="POST" action="{{ route('start') }}" class="mt-8" x-data="draftKeeper" x-on:submit="clear">
                @csrf
                <label for="message" class="sr-only">{{ __('home.textarea_label') }}</label>
                <textarea id="message" name="message" rows="6" required minlength="2" maxlength="{{ config('aisa.limits.message_max_chars') }}"
                    x-ref="input" x-on:input="save"
                    class="field min-h-40 resize-y text-lg leading-relaxed shadow-sm"
                    placeholder="{{ __('home.placeholder') }}"
                    aria-describedby="message-hint @error('message') message-error @enderror">{{ old('message') }}</textarea>
                @error('message')
                    <p id="message-error" class="field-error">{{ $message }}</p>
                @enderror
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="message-hint" class="flex items-center gap-1.5 text-sm text-ink-muted">
                        <i data-lucide="lock" class="h-4 w-4"></i>{{ __('home.private_hint') }}
                    </p>
                    <button type="submit" class="btn-primary sm:px-7">
                        {{ __('home.start') }}
                        <i data-lucide="arrow-right" class="h-5 w-5"></i>
                    </button>
                </div>
            </form>

            <p class="mt-6 text-sm text-ink-muted">
                {{ __('home.have_code') }} <a href="{{ route('check.show') }}">{{ __('home.check_code') }}</a>
            </p>
        </div>
    </div>
</section>

<section class="container-page py-14">
    <ul class="grid gap-6 md:grid-cols-3">
        @foreach ([['user-round', 'trust_account'], ['shield-check', 'trust_private'], ['heart-handshake', 'trust_choice']] as [$icon, $key])
            <li class="card">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-sage-light text-forest">
                    <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                </span>
                <h2 class="mt-4 text-xl">{{ __("home.{$key}_title") }}</h2>
                <p class="mt-2 text-ink-soft">{{ __("home.{$key}_body") }}</p>
            </li>
        @endforeach
    </ul>
</section>

<section class="container-page pb-6">
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <span class="badge-sky"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('home.level1_badge') }}</span>
            <h2 class="mt-3 text-2xl">{{ __('home.level1_title', ['name' => config('aisa.assistant_name')]) }}</h2>
            <p class="mt-2 text-ink-soft">{{ __('home.level1_body') }}</p>
        </div>
        <div class="card">
            <span class="badge-forest"><i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>{{ __('home.level2_badge') }}</span>
            <h2 class="mt-3 text-2xl">{{ __('home.level2_title') }}</h2>
            <p class="mt-2 text-ink-soft">{{ __('home.level2_body', ['hours' => config('aisa.response_window_hours')]) }}</p>
        </div>
    </div>
</section>

<section class="container-page py-10">
    <div class="reading alert-danger">
        <i data-lucide="life-buoy" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
        <div>
            <p class="font-semibold">{{ __('home.not_emergency_title') }}</p>
            <p class="mt-1">{{ __('home.not_emergency_body') }}</p>
            <a href="{{ route('get-help') }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-clay">{{ __('ui.get_help_now') }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a>
        </div>
    </div>
</section>
@endsection
