@extends('layouts.public')

@section('title', __('consent.title'))

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('consent.back') }}</a>
        <h1 class="mt-4 text-3xl sm:text-4xl">{{ __('consent.heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('consent.intro') }}</p>

        <div class="card mt-8">
            <ul class="space-y-4">
                @foreach (['collect', 'ai', 'who', 'retention', 'delete', 'limits'] as $point)
                    <li class="flex gap-3">
                        <i data-lucide="check" class="mt-1 h-5 w-5 shrink-0 text-forest"></i>
                        <div>
                            <p class="font-semibold text-ink">{{ __("consent.{$point}_title") }}</p>
                            <p class="text-ink-soft">{{ __("consent.{$point}_body", ['name' => config('aisa.assistant_name'), 'days' => config('aisa.retention_days')]) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-5 text-sm text-ink-muted">
                {{ __('consent.read_more') }} <a href="{{ route('privacy') }}">{{ __('ui.nav_privacy') }}</a> · <a href="{{ route('terms') }}">{{ __('ui.nav_terms') }}</a>
            </p>
        </div>

        <form method="POST" action="{{ route('consent.store') }}" class="mt-8 space-y-7" x-data="consentToggle">
            @csrf

            <fieldset>
                <legend class="field-label">{{ __('consent.age_question') }}</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach (['adult', 'minor'] as $group)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line-strong bg-white px-4 py-3 has-[:checked]:border-forest has-[:checked]:bg-mint">
                            <input type="radio" name="age_group" value="{{ $group }}" class="h-5 w-5 accent-forest" @checked(old('age_group') === $group) required>
                            <span>{{ __("consent.age_{$group}") }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="field-hint">{{ __('consent.age_hint_'.$minorPolicy) }}</p>
                @error('age_group') <p class="field-error">{{ $message }}</p> @enderror
            </fieldset>

            <div>
                <label for="display_name" class="field-label">{{ __('consent.name_label') }} <span class="font-normal text-ink-muted">({{ __('ui.optional') }})</span></label>
                <input id="display_name" name="display_name" type="text" maxlength="40" autocomplete="off" class="field max-w-sm" value="{{ old('display_name') }}">
                <p class="field-hint">{{ __('consent.name_hint') }}</p>
                @error('display_name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="agree" value="1" class="mt-1 h-5 w-5 shrink-0 accent-forest" x-ref="box" x-on:change="sync" @checked(old('agree'))>
                    <span>{{ __('consent.agree_label') }}</span>
                </label>
                @error('agree') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="submit" form="decline-form" class="btn-secondary">{{ __('consent.decline') }}</button>
                <button type="submit" class="btn-primary" x-bind:disabled="blocked">
                    {{ __('consent.continue') }}<i data-lucide="arrow-right" class="h-5 w-5"></i>
                </button>
            </div>
            <p class="text-sm text-ink-muted">{{ __('consent.version', ['version' => config('aisa.consent_version')]) }}</p>
        </form>
        <form id="decline-form" method="POST" action="{{ route('consent.decline') }}" class="hidden">@csrf</form>
    </div>
</div>
@endsection
