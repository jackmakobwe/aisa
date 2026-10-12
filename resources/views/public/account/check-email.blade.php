@extends('layouts.public')

@section('title', __('account.check_title'))

@section('content')
<div class="container-page py-12 sm:py-16">
    <div class="card mx-auto max-w-xl p-7 sm:p-10">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-sage-light text-forest">
            <i data-lucide="send" class="h-6 w-6"></i>
        </span>
        <h1 class="mt-5 text-3xl">{{ __('account.check_heading') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('account.check_body') }}</p>
        <p class="mt-3 text-ink-soft">{{ __('account.check_spam') }}</p>
        <x-dev-mail-note class="mt-5" />
        <p class="mt-6 border-t border-line pt-5 text-sm text-ink-soft">{{ __('account.check_existing') }}</p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('account.login') }}" class="btn-primary">{{ __('account.login_button') }}</a>
            <a href="{{ route('account.password.request') }}" class="btn-secondary">{{ __('account.forgot') }}</a>
        </div>
    </div>
</div>
@endsection
