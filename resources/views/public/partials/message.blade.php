@php($sender = $message->sender)
@if ($sender === \App\Models\Message::SENDER_USER)
    <li class="flex flex-col items-end">
        <span class="mb-1 text-xs font-semibold text-ink-muted">{{ __('chat.you') }}</span>
        <div class="bubble-user">{{ $message->body }}</div>
        <time class="mt-1 text-xs text-ink-muted" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('aisa.display_timezone'))->format('j M, H:i') }}</time>
    </li>
@elseif ($sender === \App\Models\Message::SENDER_AI)
    <li class="flex flex-col items-start">
        <span class="mb-1 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-sky">
            <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
            {{ __('chat.ai_label', ['name' => config('aisa.assistant_name')]) }}
            @if ($message->is_mock)
                <span class="badge-amber">{{ __('chat.mock_badge') }}</span>
            @endif
        </span>
        <div class="bubble-ai">{{ $message->body }}</div>
        <time class="mt-1 text-xs text-ink-muted" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('aisa.display_timezone'))->format('j M, H:i') }}</time>
    </li>
@elseif ($sender === \App\Models\Message::SENDER_HUMAN)
    <li class="flex flex-col items-start">
        <span class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-forest">
            <i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>
            {{ __('chat.human_label') }}
        </span>
        <div class="bubble-human">{{ $message->body }}</div>
        <time class="mt-1 text-xs text-ink-muted" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('aisa.display_timezone'))->format('j M, H:i') }}</time>
    </li>
@elseif ($message->body === \App\Services\ConversationService::SAFETY_NOTICE)
    <li>
        <div class="alert-danger" role="alert">
            <i data-lucide="life-buoy" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ __('chat.safety_title') }}</p>
                <p class="mt-1">{{ __('chat.safety_body') }}</p>
                <div class="mt-3">
                    <x-crisis-resources :resources="$resources" compact />
                </div>
                <p class="mt-3 text-sm">{{ __('chat.safety_limits', ['hours' => config('aisa.response_window_hours')]) }}</p>
                <a href="{{ route('get-help') }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-clay">{{ __('ui.get_help_now') }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </div>
        </div>
    </li>
@endif
