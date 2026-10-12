@extends('layouts.public')

@section('title', __('account.register_title'))

@php
    $termsLink = '<a href="'.e(route('terms')).'">'.e(__('account.terms')).'</a>';
    $privacyLink = '<a href="'.e(route('privacy')).'">'.e(__('account.privacy')).'</a>';
@endphp

@section('content')
<x-auth-shell>
    <h1 class="text-3xl">{{ __('account.register_heading') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('account.register_intro') }}</p>

    @if ($googleEnabled)
        <x-google-button class="mt-6" />
        <p class="mt-2 text-xs text-ink-muted">{!! __('account.google_terms', ['terms' => $termsLink, 'privacy' => $privacyLink]) !!}</p>
        <div class="my-6 flex items-center gap-3 text-sm text-ink-muted" aria-hidden="true">
            <span class="h-px flex-1 bg-line"></span>{{ __('account.or') }}<span class="h-px flex-1 bg-line"></span>
        </div>
    @endif

    <form method="POST" action="{{ route('account.register.store') }}" @class(['space-y-5', 'mt-6' => ! $googleEnabled]) novalidate>
        @csrf
        <div>
            <label for="name" class="field-label">{{ __('account.name') }} <span class="font-normal text-ink-muted">({{ __('ui.optional') }})</span></label>
            <input id="name" name="name" type="text" maxlength="50" autocomplete="nickname" value="{{ old('name') }}" class="field" aria-describedby="name-hint">
            <p id="name-hint" class="field-hint">{{ __('account.name_hint') }}</p>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="field-label">{{ __('account.email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="field-label">{{ __('account.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required class="field" aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
            <p id="password-hint" class="field-hint">{{ __('account.password_hint') }}</p>
            @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">{{ __('account.password_confirm') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="field">
        </div>
        <div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-forest" @checked(old('terms')) required>
                <span>{!! __('account.terms_label', ['terms' => $termsLink, 'privacy' => $privacyLink]) !!}</span>
            </label>
            @error('terms') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary w-full">{{ __('account.register_button') }}</button>
    </form>

    <p class="mt-6 text-sm text-ink-soft">
        {{ __('account.have_account') }} <a href="{{ route('account.login') }}" class="font-semibold">{{ __('account.login_button') }}</a>
    </p>
    <p class="mt-6 border-t border-line pt-5 text-sm text-ink-muted">{{ __('account.shared_device') }}</p>
</x-auth-shell>
@endsection
