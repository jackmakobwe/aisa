@extends('layouts.public')

@section('title', __('chat.title'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<div class="container-page py-8 lg:py-10">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,720px)_minmax(0,1fr)] lg:justify-center">

        {{-- Conversation column --}}
        <section aria-labelledby="chat-heading"
            x-data="chat"
            data-max="{{ config('aisa.limits.message_max_chars') }}"
            data-retry-url="{{ route('conversation.retry') }}"
            data-check-url="{{ route('check.show') }}"
            data-can-retry="{{ $canRetry ? '1' : '0' }}"
            data-generic-error="{{ __('chat.generic_error') }}"
            data-network-error="{{ __('chat.network_error') }}">

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 id="chat-heading" class="text-2xl sm:text-3xl">{{ __('chat.heading') }}</h1>
                @if ($ticket->wantsHuman())
                    <span class="badge-forest"><i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>{{ __('chat.mode_human') }}</span>
                @else
                    <span class="badge-sky"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('chat.mode_ai', ['name' => config('aisa.assistant_name')]) }}</span>
                @endif
            </div>

            @if ($ticket->wantsHuman())
                <div class="alert-info mb-6">
                    <i data-lucide="clock" class="mt-0.5 h-5 w-5 shrink-0 text-sky"></i>
                    <p>
                        @if ($ticket->status === \App\Models\Ticket::STATUS_ANSWERED)
                            {{ __('chat.human_answered') }}
                        @else
                            {{ __('chat.human_waiting', ['hours' => config('aisa.response_window_hours')]) }}
                        @endif
                    </p>
                </div>
            @endif

            <ol x-ref="list" class="space-y-5" aria-live="polite" aria-relevant="additions" aria-label="{{ __('chat.messages_label') }}">
                @foreach ($messages as $message)
                    @include('public.partials.message', ['message' => $message])
                @endforeach
            </ol>

            <div class="mt-5 flex items-center gap-2 text-sm text-ink-muted" x-show="sending" x-cloak role="status">
                <span class="flex gap-1" aria-hidden="true"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>
                {{ $ticket->wantsHuman() ? __('chat.sending') : __('chat.thinking', ['name' => config('aisa.assistant_name')]) }}
            </div>

            @if (session('ai_error'))
                <div class="alert-warning mt-5" role="alert" x-show="isIdle">
                    <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
                    <p>{{ __('chat.ai_unavailable') }}</p>
                </div>
            @endif

            <div class="alert-warning mt-5" role="alert" x-show="hasError" x-cloak>
                <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
                <p x-text="error"></p>
            </div>

            @unless ($ticket->wantsHuman())
                <div class="mt-4" x-show="canRetry" x-cloak>
                    <form method="POST" action="{{ route('conversation.retry') }}" x-on:submit.prevent="retry">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm" x-bind:disabled="sending">
                            <i data-lucide="rotate-cw" class="h-4 w-4"></i>{{ __('chat.retry') }}
                        </button>
                    </form>
                </div>

                <div class="card mt-5 border-forest/30 bg-mint" x-show="suggestHuman" x-cloak>
                    <p class="font-semibold">{{ __('chat.suggest_human_title') }}</p>
                    <p class="mt-1 text-ink-soft">{{ __('chat.suggest_human_body') }}</p>
                    <a href="{{ route('help.show') }}" class="btn-primary btn-sm mt-3">{{ __('chat.choose_help') }}</a>
                </div>
            @endunless

            @if ($pendingDeletion)
                <div class="alert-info mt-6"><i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-sky"></i><p>{{ __('chat.deletion_pending') }}</p></div>
            @else
                <form x-ref="form" method="POST" action="{{ route('conversation.messages') }}" x-on:submit.prevent="submit" class="mt-8">
                    @csrf
                    <label for="message" class="field-label">{{ $ticket->wantsHuman() ? __('chat.composer_label_human') : __('chat.composer_label') }}</label>
                    <textarea id="message" name="message" rows="4" required minlength="2" maxlength="{{ config('aisa.limits.message_max_chars') }}"
                        x-ref="input" x-on:input="onInput" x-on:keydown="onKeydown"
                        class="field resize-y" placeholder="{{ __('chat.placeholder') }}" aria-describedby="composer-hint">{{ old('message') }}</textarea>
                    @error('message') <p class="field-error">{{ $message }}</p> @enderror
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <p id="composer-hint" class="text-sm text-ink-muted">
                            <span class="hidden [@media(pointer:fine)]:inline">{{ __('chat.enter_hint') }} · </span>
                            <span x-text="counter" x-bind:class="counterClass"></span>
                        </p>
                        <button type="submit" class="btn-primary" x-bind:disabled="sending">
                            <i data-lucide="send" class="h-4 w-4"></i>{{ __('chat.send') }}
                        </button>
                    </div>
                </form>
            @endif
        </section>

        {{-- Side panel: privacy, help, choices. Below the chat on small screens. --}}
        <aside class="space-y-5 lg:sticky lg:top-24 lg:self-start" aria-label="{{ __('chat.side_label') }}">
            <div class="card border-clay/25 bg-clay-light/60 p-5">
                <p class="flex items-center gap-2 font-semibold text-clay"><i data-lucide="life-buoy" class="h-5 w-5"></i>{{ __('chat.side_help_title') }}</p>
                <p class="mt-1 text-sm">{{ __('chat.side_help_body') }}</p>
                <a href="{{ route('get-help') }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-clay">{{ __('ui.get_help_now') }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </div>

            @unless ($ticket->help_mode)
                <div class="card p-5">
                    <p class="font-semibold">{{ __('chat.side_choose_title') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('chat.side_choose_body') }}</p>
                    <a href="{{ route('help.show') }}" class="btn-secondary btn-sm mt-3 w-full">{{ __('chat.choose_help') }}</a>
                </div>
            @endunless

            @php $member = auth('member')->user(); @endphp
            <div class="card p-5">
                <p class="flex items-center gap-2 font-semibold"><i data-lucide="key-round" class="h-5 w-5 text-forest"></i>{{ __('chat.side_code_title') }}</p>
                @if ($ticket->member_id !== null && $member?->id === $ticket->member_id)
                    <p class="mt-1 text-sm text-ink-soft">{{ __('account.side_saved') }}</p>
                    <a href="{{ route('account.index') }}" class="btn-secondary btn-sm mt-3 w-full"><i data-lucide="user-round" class="h-4 w-4"></i>{{ __('account.nav_account') }}</a>
                @elseif ($ticket->member_id === null && $member?->hasVerifiedEmail())
                    <p class="mt-1 text-sm text-ink-soft">{{ __('account.side_save_body') }}</p>
                    <form method="POST" action="{{ route('account.save-current') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm w-full">{{ __('account.save_current') }}</button>
                    </form>
                @elseif ($ticket->hasCode())
                    <p class="mt-1 text-sm text-ink-soft">{{ __('chat.side_code_has') }}</p>
                @else
                    <p class="mt-1 text-sm text-ink-soft">{{ __('chat.side_code_none') }}</p>
                    <form method="POST" action="{{ route('code.issue') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm w-full">{{ __('chat.get_code') }}</button>
                    </form>
                @endif
                @if ($ticket->member_id === null && ! $member)
                    <a href="{{ route('account.login') }}" class="mt-3 inline-block text-sm">{{ __('account.side_sign_in') }}</a>
                @endif
            </div>

            <div class="card p-5">
                <p class="flex items-center gap-2 font-semibold"><i data-lucide="lock" class="h-5 w-5 text-forest"></i>{{ __('chat.side_privacy_title') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink-soft">
                    <li>{{ __('chat.side_privacy_1') }}</li>
                    <li>{{ __('chat.side_privacy_2') }}</li>
                    <li>{{ __('chat.side_privacy_3') }}</li>
                </ul>
            </div>

            @if ($hasHumanReply && ! $ticket->rating)
                <form method="POST" action="{{ route('conversation.rating') }}" class="card p-5">
                    @csrf
                    <fieldset>
                        <legend class="font-semibold">{{ __('chat.rating_question') }}</legend>
                        <div class="mt-3 flex gap-1.5">
                            @for ($i = 1; $i <= 5; $i++)
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" required>
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-line-strong font-semibold peer-checked:border-forest peer-checked:bg-forest peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-forest">{{ $i }}</span>
                                    <span class="sr-only">{{ __('chat.rating_value', ['value' => $i]) }}</span>
                                </label>
                            @endfor
                        </div>
                        <p class="mt-2 text-xs text-ink-muted">{{ __('chat.rating_scale') }}</p>
                    </fieldset>
                    <button type="submit" class="btn-secondary btn-sm mt-3">{{ __('chat.rating_submit') }}</button>
                </form>
            @endif

            <div class="card space-y-3 p-5">
                <form method="POST" action="{{ route('conversation.leave') }}">
                    @csrf
                    <button type="submit" class="btn-quiet btn-sm w-full justify-start"><i data-lucide="log-out" class="h-4 w-4"></i>{{ __('chat.leave') }}</button>
                </form>
                @unless ($pendingDeletion)
                    <details class="group">
                        <summary class="btn-quiet btn-sm w-full cursor-pointer list-none justify-start text-clay"><i data-lucide="trash-2" class="h-4 w-4"></i>{{ __('chat.delete') }}</summary>
                        <form method="POST" action="{{ route('conversation.delete') }}" class="mt-3 space-y-3">
                            @csrf
                            <p class="text-sm text-ink-soft">{{ __('chat.delete_explain') }}</p>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="confirm_delete" value="1" class="mt-0.5 h-4 w-4 accent-clay" required>
                                <span>{{ __('chat.delete_confirm') }}</span>
                            </label>
                            @error('confirm_delete') <p class="field-error">{{ $message }}</p> @enderror
                            <button type="submit" class="btn-danger btn-sm w-full">{{ __('chat.delete_submit') }}</button>
                        </form>
                    </details>
                @endunless
            </div>
        </aside>
    </div>
</div>
@endsection
