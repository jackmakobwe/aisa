@extends('layouts.public')

@section('title', __('ticket.check_title'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-3xl sm:text-4xl">{{ __('ticket.check_heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('ticket.check_intro') }}</p>

        @if ($remembered)
            <div class="card mt-8">
                <p class="font-semibold">{{ __('ticket.remembered_title') }}</p>
                <p class="mt-1 text-sm text-ink-soft">{{ __('ticket.remembered_body') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('check.remembered') }}">
                        @csrf
                        <button type="submit" class="btn-primary">{{ __('ticket.remembered_open') }}</button>
                    </form>
                    <form method="POST" action="{{ route('forget-device') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">{{ __('ticket.forget_device') }}</button>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('check.store') }}" class="card mt-8">
            @csrf
            <label for="code" class="field-label">{{ __('ticket.code_label') }}</label>
            <input id="code" name="code" type="text" required autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64"
                class="field font-mono text-lg tracking-wider" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                aria-describedby="code-hint @error('code') code-error @enderror">
            <p id="code-hint" class="field-hint">{{ __('ticket.code_hint') }}</p>
            @error('code') <p id="code-error" class="field-error" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary mt-5">{{ __('ticket.open') }}<i data-lucide="arrow-right" class="h-5 w-5"></i></button>
        </form>

        <p class="mt-6 text-sm text-ink-muted">{{ __('ticket.lost_short') }}</p>
    </div>
</div>
@endsection
