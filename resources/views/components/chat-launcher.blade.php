@php($name = config('aisa.assistant_name'))
{{-- z-30: below the sticky header (z-40), so Quick exit and Get help are never covered. --}}
<div x-data="launcher" x-on:keydown.escape="close" class="no-print fixed right-4 bottom-4 z-30 flex flex-col items-end gap-3">
    <section id="aisa-chat-panel" x-show="open" x-cloak role="dialog" aria-labelledby="aisa-chat-title"
        class="flex h-[min(38rem,calc(100dvh-10.5rem))] w-[min(26rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-line bg-ivory shadow-[0_24px_60px_-20px_rgba(34,40,43,0.45)]">

        <header class="flex items-center gap-3 bg-forest px-4 py-3 text-white">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15">
                <i data-lucide="sparkles" class="h-5 w-5"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="aisa-chat-title" x-ref="heading" tabindex="-1" class="font-sans text-base font-semibold text-white focus:outline-none">{{ __('widget.title', ['name' => $name]) }}</h2>
                <p class="text-xs text-white/80">{{ __('widget.subtitle') }}</p>
            </div>
            <button type="button" x-on:click="close" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white hover:bg-white/15 focus-visible:outline-white" aria-label="{{ __('widget.close') }}">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </header>

        @if ($ticket)
            {{-- Continue the conversation this session already has. --}}
            <div x-data="chat" class="flex min-h-0 flex-1 flex-col"
                data-max="{{ config('aisa.limits.message_max_chars') }}"
                data-retry-url="{{ route('conversation.retry') }}"
                data-check-url="{{ route('check.show') }}"
                data-can-retry="0"
                data-generic-error="{{ __('chat.generic_error') }}"
                data-network-error="{{ __('chat.network_error') }}">

                <div data-chat-scroll="end" class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                    @if ($ticket->wantsHuman())
                        <p class="alert-info mb-4 text-sm"><i data-lucide="heart-handshake" class="mt-0.5 h-4 w-4 shrink-0 text-sky"></i>{{ __('widget.human_mode') }}</p>
                    @endif
                    <ol x-ref="list" class="space-y-4" aria-live="polite" aria-relevant="additions" aria-label="{{ __('chat.messages_label') }}">
                        @foreach ($messages as $message)
                            @include('public.partials.message', ['message' => $message, 'resources' => $resources])
                        @endforeach
                    </ol>
                    <div class="mt-4 flex items-center gap-2 text-sm text-ink-muted" x-show="sending" x-cloak role="status">
                        <span class="flex gap-1" aria-hidden="true"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></span>
                        {{ $ticket->wantsHuman() ? __('chat.sending') : __('chat.thinking', ['name' => $name]) }}
                    </div>
                    <div class="alert-warning mt-4 text-sm" role="alert" x-show="hasError" x-cloak>
                        <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber"></i>
                        <p x-text="error"></p>
                    </div>
                </div>

                @unless ($ticket->hasPendingDeletion())
                    <form x-ref="form" method="POST" action="{{ route('conversation.messages') }}" x-on:submit.prevent="submit" class="border-t border-line bg-white p-3">
                        @csrf
                        <label for="aisa-chat-message" class="sr-only">{{ __('widget.message_label') }}</label>
                        <textarea id="aisa-chat-message" name="message" rows="2" required minlength="2" maxlength="{{ config('aisa.limits.message_max_chars') }}"
                            data-chat-input x-ref="input" x-on:input="onInput" x-on:keydown="onKeydown"
                            class="field resize-none text-[0.95rem]" placeholder="{{ __('chat.placeholder') }}"></textarea>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <a href="{{ route('conversation.show') }}" class="text-sm">{{ __('widget.open_full') }}</a>
                            <button type="submit" class="btn-primary btn-sm" x-bind:disabled="sending">
                                <i data-lucide="send" class="h-4 w-4"></i>{{ __('chat.send') }}
                            </button>
                        </div>
                    </form>
                @endunless
            </div>
        @else
            {{-- New visitor: the first message goes through the normal consent step. --}}
            <div data-chat-scroll class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                <span class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-sky">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('chat.ai_label', ['name' => $name]) }}
                </span>
                <div class="bubble-ai">{{ __('widget.greeting', ['name' => $name]) }}</div>

                <p class="mt-5 text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ __('widget.examples_label') }}</p>
                <ul class="mt-2 space-y-2">
                    @foreach (__('home.modules') as $key => $module)
                        <li>
                            <button type="button" x-on:click="useExample" data-module="{{ $key }}" data-text="{{ $module['examples'][0] }}"
                                class="flex w-full items-start gap-2 rounded-xl border border-line bg-white px-3 py-2 text-left text-sm text-ink-soft hover:border-sage hover:text-ink">
                                <i data-lucide="{{ $module['icon'] }}" class="mt-0.5 h-4 w-4 shrink-0 text-forest"></i>
                                <span>{{ $module['examples'][0] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <form method="POST" action="{{ route('start') }}" class="border-t border-line bg-white p-3">
                @csrf
                <input type="hidden" name="module" value="" x-ref="module">
                <label for="aisa-chat-start" class="sr-only">{{ __('widget.message_label') }}</label>
                <textarea id="aisa-chat-start" name="message" rows="2" required minlength="2" maxlength="{{ config('aisa.limits.message_max_chars') }}"
                    data-chat-input class="field resize-none text-[0.95rem]" placeholder="{{ __('widget.placeholder') }}" aria-describedby="aisa-chat-start-hint"></textarea>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <p id="aisa-chat-start-hint" class="flex items-start gap-1.5 text-xs text-ink-muted"><i data-lucide="lock" class="mt-px h-3.5 w-3.5 shrink-0"></i>{{ __('widget.consent_hint') }}</p>
                    <button type="submit" class="btn-primary btn-sm shrink-0">
                        {{ __('widget.send') }}<i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                </div>
            </form>
        @endif
    </section>

    <a href="{{ $ticket ? route('conversation.show') : route('home').'#start' }}" x-ref="button" x-on:click="toggle"
        x-bind:aria-expanded="expanded" aria-controls="aisa-chat-panel"
        class="inline-flex items-center gap-2 rounded-full bg-forest px-5 py-3 font-semibold text-white no-underline shadow-[0_12px_30px_-10px_rgba(34,40,43,0.6)] transition-colors hover:bg-forest-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest">
        <span x-show="closed" class="inline-flex"><i data-lucide="message-circle" class="h-5 w-5"></i></span>
        <span x-show="open" x-cloak class="inline-flex"><i data-lucide="x" class="h-5 w-5"></i></span>
        <span class="hidden sm:inline">{{ __('widget.open', ['name' => $name]) }}</span>
        <span class="sm:hidden">{{ __('widget.open_short') }}</span>
    </a>
</div>
