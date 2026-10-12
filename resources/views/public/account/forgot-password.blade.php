@extends('layouts.public')

@section('title', __('account.forgot_title'))

@section('content')
<div class="container-page py-12 sm:py-16">
    <div class="card mx-auto max-w-xl p-7 sm:p-10">
        <h1 class="text-3xl">{{ __('account.forgot_heading') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('account.forgot_intro') }}</p>
        <x-dev-mail-note class="mt-5" />

        <form method="POST" action="{{ route('account.password.email') }}" class="mt-6 space-y-5" novalidate>
            @csrf
            <div>
                <label for="email" class="field-label">{{ __('account.email') }}</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('account.forgot_button') }}</button>
        </form>

        <a href="{{ route('account.login') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('account.back_to_login') }}
        </a>
    </div>
</div>
@endsection
