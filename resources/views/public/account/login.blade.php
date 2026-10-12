@extends('layouts.public')

@section('title', __('account.login_title'))

@section('content')
<x-auth-shell>
    <h1 class="text-3xl">{{ __('account.login_heading') }}</h1>
    <p class="mt-2 text-ink-soft">{{ __('account.login_intro') }}</p>

    @if ($errors->any())
        <p class="alert-danger mt-6" role="alert">
            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <span>{{ $errors->first() }}</span>
        </p>
    @endif

    @if ($googleEnabled)
        <x-google-button class="mt-6" />
        <div class="my-6 flex items-center gap-3 text-sm text-ink-muted" aria-hidden="true">
            <span class="h-px flex-1 bg-line"></span>{{ __('account.or') }}<span class="h-px flex-1 bg-line"></span>
        </div>
    @endif

    <form method="POST" action="{{ route('account.login.store') }}" @class(['space-y-5', 'mt-6' => ! $googleEnabled]) novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">{{ __('account.email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="field" @error('email') aria-invalid="true" @enderror>
        </div>
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <label for="password" class="field-label">{{ __('account.password') }}</label>
                <a href="{{ route('account.password.request') }}" class="text-sm">{{ __('account.forgot') }}</a>
            </div>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="field">
        </div>
        <button type="submit" class="btn-primary w-full">{{ __('account.login_button') }}</button>
    </form>

    <p class="mt-6 text-sm text-ink-soft">
        {{ __('account.no_account') }} <a href="{{ route('account.register') }}" class="font-semibold">{{ __('account.create_account_link') }}</a>
    </p>
    <p class="mt-2 text-sm text-ink-soft">
        {{ __('account.have_code') }} <a href="{{ route('check.show') }}">{{ __('home.check_code') }}</a>
    </p>
    <p class="mt-6 border-t border-line pt-5 text-sm text-ink-muted">{{ __('account.shared_device') }}</p>
</x-auth-shell>
@endsection
