@extends('layouts.public')

@section('title', __('help.title'))

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<div class="container-page py-12">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('conversation.show') }}" class="inline-flex items-center gap-1 text-sm"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('help.back') }}</a>
        <h1 class="mt-4 text-3xl sm:text-4xl">{{ __('help.heading') }}</h1>
        <p class="mt-3 max-w-2xl text-lg text-ink-soft">{{ __('help.intro') }}</p>

        <div class="mt-8 grid gap-6 md:grid-cols-2">
            {{-- Option 1: AI guidance. Presented as a full, equal option. --}}
            <form method="POST" action="{{ route('help.store') }}" class="card flex flex-col">
                @csrf
                <input type="hidden" name="mode" value="ai">
                <span class="badge-sky self-start"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('help.ai_badge') }}</span>
                <h2 class="mt-3 text-2xl">{{ __('help.ai_title') }}</h2>
                <ul class="mt-4 flex-1 space-y-2 text-ink-soft">
                    @foreach (['ai_point_1', 'ai_point_2', 'ai_point_3', 'ai_point_4'] as $point)
                        <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ __("help.{$point}", ['name' => config('aisa.assistant_name')]) }}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btn-secondary mt-6" @disabled($ticket->help_mode === 'ai')>
                    {{ $ticket->help_mode === 'ai' ? __('help.ai_current') : __('help.ai_button') }}
                </button>
            </form>

            {{-- Option 2: Talk to Aisa. --}}
            <form method="POST" action="{{ route('help.store') }}" class="card flex flex-col border-forest/30" x-data="consentToggle">
                @csrf
                <input type="hidden" name="mode" value="human">
                <span class="badge-forest self-start"><i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>{{ __('help.human_badge') }}</span>
                <h2 class="mt-3 text-2xl">{{ __('help.human_title') }}</h2>
                <ul class="mt-4 space-y-2 text-ink-soft">
                    @foreach (['human_point_1', 'human_point_2', 'human_point_3', 'human_point_4'] as $point)
                        <li class="flex gap-2"><i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i>{{ __("help.{$point}", ['hours' => config('aisa.response_window_hours')]) }}</li>
                    @endforeach
                </ul>

                <div class="alert-warning mt-5 text-sm">
                    <i data-lucide="clock" class="mt-0.5 h-4 w-4 shrink-0 text-amber"></i>
                    <p>{{ __('help.not_monitored') }}</p>
                </div>

                @if ($ticket->wantsHuman())
                    <p class="mt-6 rounded-xl bg-sage-light px-4 py-3 text-sm font-semibold text-forest-dark">{{ __('help.human_current') }}</p>
                @elseif (! $humanOpen)
                    <p class="mt-6 rounded-xl bg-amber-light px-4 py-3 text-sm">{{ __('help.paused') }}</p>
                @else
                    <label class="mt-5 flex items-start gap-3 text-sm">
                        <input type="checkbox" name="share_ok" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-forest" x-ref="box" x-on:change="sync">
                        <span>{{ __('help.share_label') }}</span>
                    </label>
                    @error('share_ok') <p class="field-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn-primary mt-4" x-bind:disabled="blocked">{{ __('help.human_button') }}</button>
                @endif
            </form>
        </div>

        <p class="mt-8 text-sm text-ink-muted">{{ __('help.change_later') }}</p>
    </div>
</div>
@endsection
