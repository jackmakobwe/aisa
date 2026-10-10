@extends('layouts.public')

@section('title', __('ticket.issued_title'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-3xl sm:text-4xl">{{ __('ticket.issued_heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('ticket.issued_intro') }}</p>

        <div class="card mt-8 text-center" x-data="copyCode" data-code="{{ $code }}" data-copied-text="{{ __('ticket.copied') }}">
            <p class="text-sm font-semibold tracking-wide text-ink-muted uppercase">{{ __('ticket.your_code') }}</p>
            <p x-ref="code" class="mt-3 font-mono text-xl font-bold tracking-wider break-all text-ink sm:text-2xl" aria-label="{{ __('ticket.your_code') }}: {{ implode(' ', str_split(str_replace('-', '', $code))) }}">{{ $code }}</p>
            <div class="no-print mt-6 flex flex-wrap justify-center gap-3">
                <button type="button" class="btn-primary" x-on:click="copy">
                    <i data-lucide="copy" class="h-4 w-4"></i>
                    <span x-show="copied" x-cloak>{{ __('ticket.copied') }}</span>
                    <span x-show="notCopied">{{ __('ticket.copy') }}</span>
                </button>
                <button type="button" class="btn-secondary" x-on:click="print">
                    <i data-lucide="printer" class="h-4 w-4"></i>{{ __('ticket.print') }}
                </button>
            </div>
            <p class="sr-only" role="status" x-text="announcement"></p>
        </div>

        <div class="alert-warning mt-6">
            <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
            <div>
                <p class="font-semibold">{{ __('ticket.lost_title') }}</p>
                <p class="mt-1">{{ __('ticket.lost_body') }}</p>
            </div>
        </div>

        <ul class="mt-6 space-y-2 text-ink-soft">
            <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ __('ticket.tip_1') }}</li>
            <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ __('ticket.tip_2') }}</li>
            <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ __('ticket.tip_3', ['url' => route('check.show')]) }}</li>
        </ul>

        <form method="POST" action="{{ route('code.saved') }}" class="no-print card mt-8 space-y-5">
            @csrf
            <label class="flex items-start gap-3">
                <input type="checkbox" name="remember" value="1" class="mt-1 h-5 w-5 shrink-0 accent-forest">
                <span>
                    <span class="font-semibold">{{ __('ticket.remember_label') }}</span>
                    <span class="mt-0.5 block text-sm text-ink-muted">{{ __('ticket.remember_hint') }}</span>
                </span>
            </label>
            <button type="submit" class="btn-primary w-full sm:w-auto">{{ __('ticket.saved_button') }}</button>
        </form>
    </div>
</div>
@endsection
