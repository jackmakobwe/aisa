@extends('layouts.public')

@section('title', __('account.verify_title'))

@section('content')
<div class="container-page py-12 sm:py-16">
    <div class="card mx-auto max-w-xl p-7 sm:p-10">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-sage-light text-forest">
            <i data-lucide="mail-warning" class="h-6 w-6"></i>
        </span>
        <h1 class="mt-5 text-3xl">{{ __('account.verify_heading') }}</h1>
        <p class="mt-3 text-ink-soft">{{ __('account.verify_body') }}</p>
        <p class="mt-3 text-ink-soft">{{ __('account.check_spam') }}</p>
        <x-dev-mail-note class="mt-5" />
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <form method="POST" action="{{ route('account.verify.resend') }}">
                @csrf
                <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('account.verify_resend') }}</button>
            </form>
            <form method="POST" action="{{ route('account.logout') }}">
                @csrf
                <button type="submit" class="btn-secondary w-full sm:w-auto">{{ __('account.sign_out') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
