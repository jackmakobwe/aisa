@extends('layouts.public')

@section('title', __('account.index_title'))

@php
    $statusBadges = [
        'open' => 'badge-neutral',
        'awaiting_human' => 'badge-amber',
        'answered' => 'badge-forest',
        'closed' => 'badge-neutral',
    ];
@endphp

@section('content')
<section class="border-b border-line bg-linear-to-b from-sand/70 to-ivory">
    <div class="container-page flex flex-col gap-5 py-10 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-wide text-forest uppercase">{{ __('account.index_title') }}</p>
            <h1 class="mt-2 text-4xl">{{ $member->name ? __('account.greeting', ['name' => $member->name]) : __('account.greeting_anonymous') }}</h1>
            <p class="mt-2 text-ink-soft">{{ __('account.index_intro') }}</p>
        </div>
        <a href="{{ route('home') }}#start" class="btn-primary shrink-0 self-start sm:self-auto">
            <i data-lucide="message-circle" class="h-5 w-5"></i>{{ __('account.new_conversation') }}
        </a>
    </div>
</section>

<div class="container-page grid gap-8 py-10 lg:grid-cols-[1fr_20rem]">
    <section aria-labelledby="conversations-heading">
        @if ($unsavedTicket)
            <div class="alert-info mb-6 flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-3">
                    <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-sky"></i>
                    <div>
                        <p class="font-semibold">{{ __('account.unsaved_title') }}</p>
                        <p class="text-sm">{{ __('account.unsaved_body') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('account.save-current') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="btn-secondary btn-sm">{{ __('account.save_current') }}</button>
                </form>
            </div>
        @endif

        <h2 id="conversations-heading" class="text-2xl">{{ __('account.conversations') }}</h2>

        @if ($tickets->isEmpty())
            <div class="card mt-5 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-sage-light text-forest">
                    <i data-lucide="messages-square" class="h-6 w-6"></i>
                </span>
                <p class="mt-3 text-ink-soft">{{ __('account.no_conversations') }}</p>
                <a href="{{ route('home') }}#start" class="btn-primary btn-sm mt-4">{{ __('account.new_conversation') }}</a>
            </div>
        @else
            <ul class="mt-5 space-y-4">
                @foreach ($tickets as $ticket)
                    @php $preview = $ticket->messages->first()?->body; @endphp
                    <li class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $statusBadges[$ticket->status] ?? 'badge-neutral' }}">{{ __('account.status_'.$ticket->status) }}</span>
                                @if ($ticket->deletion_pending)
                                    <span class="badge-clay">{{ __('account.pending_deletion') }}</span>
                                @endif
                                <span class="text-sm text-ink-muted">{{ __('account.started', ['date' => $ticket->created_at->translatedFormat('j M Y')]) }}</span>
                            </div>
                            @if ($preview)
                                <p class="mt-2 truncate text-ink-soft">{{ \Illuminate\Support\Str::limit($preview, 120) }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('account.open', $ticket->reference) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto">
                                {{ __('account.open') }}<i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6 text-sm text-ink-muted">{{ __('account.retention_note', ['days' => config('aisa.retention_days')]) }}</p>
    </section>

    <aside class="space-y-5" aria-label="{{ __('account.settings') }}">
        <div class="card p-5">
            <h2 class="font-sans text-lg font-semibold">{{ __('account.settings') }}</h2>
            <p class="mt-2 text-sm break-words text-ink-soft">{{ __('account.signed_in_as', ['email' => $member->email]) }}</p>
            @if ($member->google_id_hash)
                <p class="mt-1 text-sm text-ink-soft">{{ __('account.google_linked') }}</p>
            @endif

            <form method="POST" action="{{ route('account.name') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="name" class="field-label text-sm">{{ __('account.name') }}</label>
                    <input id="name" name="name" type="text" maxlength="50" value="{{ old('name', $member->name) }}" class="field py-2">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-secondary btn-sm">{{ __('account.name_save') }}</button>
            </form>

            @if ($member->hasPassword())
                <p class="mt-4 text-sm text-ink-muted">{{ __('account.change_password') }}</p>
            @endif

            <form method="POST" action="{{ route('account.logout') }}" class="mt-4 border-t border-line pt-4">
                @csrf
                <button type="submit" class="btn-quiet btn-sm w-full justify-start"><i data-lucide="log-out" class="h-4 w-4"></i>{{ __('account.sign_out') }}</button>
            </form>
        </div>

        <details class="card p-5" @if ($errors->has('confirm_delete') || $errors->has('password')) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-2 font-semibold text-clay">
                <i data-lucide="trash-2" class="h-4 w-4"></i>{{ __('account.delete_account') }}
            </summary>
            <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                @csrf
                <p class="text-sm text-ink-soft">{{ __('account.delete_explain') }}</p>
                @if ($member->hasPassword())
                    <div>
                        <label for="delete-password" class="field-label text-sm">{{ __('account.delete_password') }}</label>
                        <input id="delete-password" name="password" type="password" autocomplete="current-password" class="field py-2">
                        @error('password') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="confirm_delete" value="1" class="mt-0.5 h-4 w-4 accent-clay" required>
                    <span>{{ __('account.delete_confirm') }}</span>
                </label>
                @error('confirm_delete') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="btn-danger btn-sm w-full">{{ __('account.delete_button') }}</button>
            </form>
        </details>
    </aside>
</div>
@endsection
