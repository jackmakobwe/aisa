@extends('layouts.public')

@section('title', __('mama.title'))

@section('content')
{{-- Header --}}
<section class="border-b border-line bg-linear-to-b from-sand/80 to-ivory">
    <div class="container-page py-12 sm:py-16">
        <div @class(['grid items-center gap-10 lg:grid-cols-[1.1fr_1fr]' => isset($photos['hero'])])>
            <div class="max-w-3xl">
                <p class="flex items-center gap-2 text-sm font-semibold tracking-wide text-forest uppercase">
                    <i data-lucide="baby" class="h-4 w-4"></i>{{ __('mama.eyebrow') }}
                </p>
                <h1 class="mt-3 text-4xl leading-tight sm:text-5xl">{{ __('mama.heading') }}</h1>
                <p class="mt-5 text-lg text-ink-soft">{{ __('mama.intro') }}</p>
                <p class="mt-3 text-ink-muted">{{ __('mama.for_whom') }}</p>
            </div>
            @isset($photos['hero'])
                <x-mama-photo key="hero" :photo="$photos['hero']" large />
            @endisset
        </div>

        @unless ($reviewed)
            <div class="alert-warning mt-8 max-w-3xl" role="note">
                <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
                <p>{{ __('mama.draft_notice') }}</p>
            </div>
        @endunless

        <nav class="mt-8" aria-label="{{ __('mama.jump_label') }}">
            <p class="text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ __('mama.jump_label') }}</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach (__('mama.sections') as $key => $section)
                    <li>
                        <a href="#{{ $key }}" @class([
                            'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium no-underline',
                            'border-clay/40 bg-clay-light text-clay hover:bg-clay hover:text-white' => ! empty($section['urgent']),
                            'border-line bg-white text-ink-soft hover:border-sage hover:text-ink' => empty($section['urgent']),
                        ])>
                            <i data-lucide="{{ $section['icon'] }}" class="h-4 w-4"></i>{{ $section['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</section>

{{-- Sections. Danger-sign lists stand out and point to emergency help. --}}
<div class="container-page py-12">
    <div class="mx-auto max-w-3xl space-y-8">
        @foreach (__('mama.sections') as $key => $section)
            @if (! empty($section['urgent']))
                <section id="{{ $key }}" class="scroll-mt-24 rounded-[1.5rem] border-2 border-clay/40 bg-clay-light/50 p-6 sm:p-8" aria-labelledby="{{ $key }}-title">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-clay text-white">
                            <i data-lucide="{{ $section['icon'] }}" class="h-5 w-5"></i>
                        </span>
                        <div>
                            <p class="text-sm font-semibold tracking-wide text-clay uppercase">{{ __('mama.urgent_label') }}</p>
                            <h2 id="{{ $key }}-title" class="text-2xl">{{ $section['title'] }}</h2>
                        </div>
                    </div>
                    <p class="mt-4 font-medium">{{ $section['intro'] }}</p>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ($section['points'] as $point)
                            <li class="flex items-start gap-2 rounded-xl bg-white px-3 py-2.5">
                                <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-clay"></i><span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('get-help') }}" class="btn mt-5 bg-clay text-white hover:bg-clay/90">
                        <i data-lucide="life-buoy" class="h-5 w-5"></i>{{ __('mama.urgent_help') }}
                    </a>
                </section>
            @else
                <section id="{{ $key }}" class="card scroll-mt-24 p-6 sm:p-8" aria-labelledby="{{ $key }}-title">
                    @isset($photos[$key])
                        <x-mama-photo :key="$key" :photo="$photos[$key]" class="mb-6" />
                    @endisset
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sage-light text-forest">
                            <i data-lucide="{{ $section['icon'] }}" class="h-5 w-5"></i>
                        </span>
                        <h2 id="{{ $key }}-title" class="text-2xl">{{ $section['title'] }}</h2>
                    </div>
                    <p class="mt-4 text-ink-soft">{{ $section['intro'] }}</p>
                    <ul class="mt-4 space-y-2.5">
                        @foreach ($section['points'] as $point)
                            <li class="flex items-start gap-2.5">
                                <i data-lucide="check" class="mt-1 h-4 w-4 shrink-0 text-forest"></i><span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        {{-- Ask privately --}}
        <section class="rounded-[1.5rem] bg-forest p-6 text-white sm:p-8" aria-labelledby="ask-title">
            <h2 id="ask-title" class="text-2xl text-white">{{ __('mama.ask_title') }}</h2>
            <p class="mt-3 text-white/85">{{ __('mama.ask_body') }}</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('home', ['topic' => 'parenting']) }}#start" class="btn bg-white text-forest-dark hover:bg-sand focus-visible:outline-white">
                    <i data-lucide="message-circle" class="h-5 w-5"></i>{{ __('mama.ask_button') }}
                </a>
                <a href="{{ route('how') }}" class="btn border border-white/40 text-white hover:bg-white/10 focus-visible:outline-white">
                    <i data-lucide="heart-handshake" class="h-5 w-5"></i>{{ __('mama.ask_human') }}
                </a>
            </div>
        </section>

        <p class="text-sm text-ink-muted">{{ __('mama.disclaimer') }}</p>
        @if ($photos)
            <p class="text-sm text-ink-muted">{{ __('mama.photos_note') }}</p>
        @endif
    </div>
</div>
@endsection
