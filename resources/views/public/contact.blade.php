@extends('layouts.public')

@section('title', __('ui.nav_contact'))

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-4xl">{{ __('contact.heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('contact.intro') }}</p>

        <div class="alert-danger mt-6">
            <i data-lucide="life-buoy" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <p>{{ __('contact.not_for_emergencies') }} <a href="{{ route('get-help') }}" class="font-semibold text-clay">{{ __('ui.get_help_now') }}</a></p>
        </div>

        <form method="POST" action="{{ route('contact.store') }}" class="card mt-8 space-y-6" x-data="consentToggle">
            @csrf
            <div>
                <label for="type" class="field-label">{{ __('contact.type_label') }}</label>
                <select id="type" name="type" class="field" required>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ __("contact.type_{$type}") }}</option>
                    @endforeach
                </select>
                @error('type') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="message" class="field-label">{{ __('contact.message_label') }}</label>
                <textarea id="message" name="message" rows="6" required minlength="5" maxlength="3000" class="field">{{ old('message') }}</textarea>
                <p class="field-hint">{{ __('contact.message_hint') }}</p>
                @error('message') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="contact" class="field-label">{{ __('contact.contact_label') }} <span class="font-normal text-ink-muted">({{ __('ui.optional') }})</span></label>
                <input id="contact" name="contact" type="text" maxlength="120" autocomplete="off" class="field" value="{{ old('contact') }}">
                <p class="field-hint">{{ __('contact.contact_hint') }}</p>
                @error('contact') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            {{-- Honeypot, hidden from people and assistive technology. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="privacy_ok" value="1" class="mt-1 h-5 w-5 shrink-0 accent-forest" x-ref="box" x-on:change="sync">
                <span>{{ __('contact.privacy_label') }}</span>
            </label>
            @error('privacy_ok') <p class="field-error">{{ $message }}</p> @enderror

            <button type="submit" class="btn-primary" x-bind:disabled="blocked">{{ __('contact.submit') }}</button>
        </form>
    </div>
</div>
@endsection
