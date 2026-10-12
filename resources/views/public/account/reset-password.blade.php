@extends('layouts.public')

@section('title', __('account.reset_title'))

@section('content')
<div class="container-page py-12 sm:py-16">
    <div class="card mx-auto max-w-xl p-7 sm:p-10">
        <h1 class="text-3xl">{{ __('account.reset_heading') }}</h1>

        <form method="POST" action="{{ route('account.password.update') }}" class="mt-6 space-y-5" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="field-label">{{ __('account.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', $email) }}" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="field-label">{{ __('account.new_password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required class="field" aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <p id="password-hint" class="field-hint">{{ __('account.password_hint') }}</p>
                @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">{{ __('account.password_confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="field">
            </div>
            <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('account.reset_button') }}</button>
        </form>
    </div>
</div>
@endsection
