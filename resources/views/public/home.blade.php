@extends('layouts.public')

@section('content')
{{-- Hero: the question and the message box. --}}
<section class="relative overflow-hidden border-b border-line bg-linear-to-b from-sand/80 to-ivory">
    <span class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-sage/20 blur-3xl" aria-hidden="true"></span>
    <span class="pointer-events-none absolute -bottom-32 -left-20 h-72 w-72 rounded-full bg-sky/10 blur-3xl" aria-hidden="true"></span>

    <div class="container-page relative grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-[1fr_1.05fr] lg:gap-14 lg:py-20">
        <div>
            <p class="mb-4 text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.eyebrow') }}</p>
            <h1 class="text-4xl leading-tight sm:text-5xl lg:text-[3.4rem]">{{ __('home.heading') }}</h1>
            <p class="mt-5 max-w-xl text-lg text-ink-soft">{{ __('home.subheading') }}</p>

            <ul class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-x-6">
                @foreach (__('home.hero_points') as $point)
                    <li class="flex items-center gap-2 font-medium text-ink">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sage-light text-forest">
                            <i data-lucide="{{ $point['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        {{ $point['text'] }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div id="start" class="scroll-mt-24 rounded-[1.5rem] border border-line bg-white p-5 shadow-[0_20px_50px_-20px_rgba(34,40,43,0.25)] sm:p-7">
            <form method="POST" action="{{ route('start') }}" x-data="draftKeeper" x-on:submit="clear">
                @csrf
                @php($chosen = old('module', $module))
                <fieldset class="mb-5">
                    <legend class="field-label">{{ __('home.module_legend') }} <span class="font-normal text-ink-muted">({{ __('home.module_optional') }})</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['' => ['icon' => 'message-circle', 'short' => __('home.module_any')]] + __('home.modules') as $key => $option)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-line px-3.5 py-1.5 text-sm font-medium text-ink-soft transition-colors hover:border-sage has-checked:border-forest has-checked:bg-sage-light has-checked:text-forest-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-forest">
                                <input type="radio" name="module" value="{{ $key }}" class="sr-only" @checked((string) $chosen === (string) $key)>
                                <i data-lucide="{{ $option['icon'] }}" class="h-4 w-4"></i>{{ $option['short'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <label for="message" class="field-label text-lg">{{ __('home.textarea_label') }}</label>
                <textarea id="message" name="message" rows="6" required minlength="2" maxlength="{{ config('aisa.limits.message_max_chars') }}"
                    x-ref="input" x-on:input="save"
                    class="field min-h-40 resize-y text-lg leading-relaxed"
                    placeholder="{{ $module ? __("home.modules.{$module}.placeholder") : __('home.placeholder') }}"
                    aria-describedby="message-hint @error('message') message-error @enderror">{{ old('message', $example) }}</textarea>
                @error('message')
                    <p id="message-error" class="field-error">{{ $message }}</p>
                @enderror
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="message-hint" class="flex items-center gap-1.5 text-sm text-ink-muted">
                        <i data-lucide="lock" class="h-4 w-4 shrink-0"></i>{{ __('home.private_hint') }}
                    </p>
                    <button type="submit" class="btn-primary shrink-0 sm:px-7">
                        {{ __('home.start') }}
                        <i data-lucide="arrow-right" class="h-5 w-5"></i>
                    </button>
                </div>
            </form>

            <p class="mt-5 border-t border-line pt-4 text-sm text-ink-muted">
                {{ __('home.have_code') }} <a href="{{ route('check.show') }}">{{ __('home.check_code') }}</a>
            </p>
        </div>
    </div>
</section>

{{-- Guidance areas: each card preselects its area and can fill in an example question. --}}
<section class="container-page py-16" aria-labelledby="modules-heading">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.modules_eyebrow') }}</p>
        <h2 id="modules-heading" class="mt-2 text-3xl leading-tight sm:text-4xl">{{ __('home.modules_heading') }}</h2>
        <p class="mt-3 text-ink-soft">{{ __('home.modules_body') }}</p>
    </div>
    <ul class="mt-10 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (__('home.modules') as $key => $item)
            <li class="card flex flex-col transition-colors hover:border-sage">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-sage-light text-forest">
                    <i data-lucide="{{ $item['icon'] }}" class="h-6 w-6"></i>
                </span>
                <h3 class="mt-4 font-sans text-lg font-semibold">{{ $item['title'] }}</h3>
                <p class="mt-1 text-[0.95rem] text-ink-soft">{{ $item['body'] }}</p>
                <p class="mt-4 text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ __('home.module_examples') }}</p>
                <ul class="mt-2 flex-1 space-y-2">
                    @foreach ($item['examples'] as $i => $question)
                        <li>
                            <a href="{{ route('home', ['topic' => $key, 'ask' => $i]) }}#start" class="flex gap-2 text-[0.95rem] leading-snug">
                                <i data-lucide="message-circle" class="mt-0.5 h-4 w-4 shrink-0"></i><span>{{ $question }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('home', ['topic' => $key]) }}#start" class="btn-secondary btn-sm mt-5 self-start">
                    {{ __('home.module_start') }}<i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </li>
        @endforeach
    </ul>

    <figure class="mx-auto mt-12 max-w-2xl rounded-[1.5rem] border border-line bg-sand/50 p-5 sm:p-7">
        <figcaption class="mb-4 text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.example_heading') }}</figcaption>
        <div class="flex flex-col gap-3">
            <div class="bubble-user">{{ __('home.example_user') }}</div>
            <div>
                <span class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-sky">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('chat.ai_label', ['name' => config('aisa.assistant_name')]) }}
                </span>
                <div class="bubble-ai">{{ __('home.example_ai') }}</div>
            </div>
        </div>
        <p class="mt-4 text-xs text-ink-muted">{{ __('home.example_note') }}</p>
    </figure>
</section>

{{-- Talk to Aisa panel. Light text on forest green; links override the default link colour and focus ring. --}}
<section class="bg-forest text-white" aria-labelledby="talk-heading">
    <div class="container-page grid gap-12 py-16 lg:grid-cols-2 lg:items-center lg:py-20">
        <div>
            <span class="badge bg-white/15 text-white"><i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>{{ __('home.level2_badge') }}</span>
            <h2 id="talk-heading" class="mt-4 text-4xl text-white sm:text-5xl">{{ __('home.talk_heading') }}</h2>
            <p class="mt-4 max-w-xl text-lg text-white/85">{{ __('home.talk_body') }}</p>

            <ol class="mt-8 space-y-5">
                @foreach (__('home.talk_steps', ['hours' => config('aisa.response_window_hours')]) as $step)
                    <li class="flex gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white font-semibold text-forest-dark" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div>
                            <p class="font-semibold text-white">{{ $step['title'] }}</p>
                            <p class="mt-0.5 text-white/80">{{ $step['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                <a href="#start" class="btn bg-white text-forest-dark hover:bg-sand focus-visible:outline-white">
                    {{ __('home.talk_start') }}<i data-lucide="arrow-right" class="h-5 w-5"></i>
                </a>
                @auth('member')
                    <a href="{{ route('account.index') }}" class="btn border border-white/40 text-white hover:bg-white/10 focus-visible:outline-white">
                        <i data-lucide="user-round" class="h-5 w-5"></i>{{ __('account.nav_account') }}
                    </a>
                @else
                    <a href="{{ route('account.login') }}" class="btn border border-white/40 text-white hover:bg-white/10 focus-visible:outline-white">
                        <i data-lucide="user-round" class="h-5 w-5"></i>{{ __('home.talk_sign_in') }}
                    </a>
                @endauth
            </div>
            <p class="mt-4 text-sm text-white/90">
                {{ __('home.talk_have_code') }}
                <a href="{{ route('check.show') }}" class="font-semibold text-white focus-visible:outline-white">{{ __('home.talk_check') }}</a>
            </p>
        </div>

        <div class="space-y-5">
            <figure class="rounded-[1.5rem] bg-white p-6 text-ink shadow-xl sm:p-8">
                <div class="flex items-center gap-4">
                    @if ($hasAisaPhoto)
                        <img src="{{ asset('images/aisa.jpg') }}" alt="{{ __('about.photo_alt') }}" class="h-20 w-20 shrink-0 rounded-full object-cover" width="80" height="80" loading="lazy">
                    @else
                        <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-sage-light" role="img" aria-label="{{ __('about.photo_placeholder') }}">
                            <x-logo class="h-11 w-11" />
                        </span>
                    @endif
                    <figcaption>
                        <p class="font-serif text-2xl font-semibold">Aisa</p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-forest">
                            <i data-lucide="user-round" class="h-4 w-4"></i>{{ __('home.talk_person') }}
                        </p>
                    </figcaption>
                </div>
                <blockquote class="mt-6 border-l-4 border-sage pl-4 text-ink-soft">
                    <p>{{ __('home.level2_body', ['hours' => config('aisa.response_window_hours')]) }}</p>
                    <p class="mt-3 flex items-start gap-2 text-sm text-ink-muted">
                        <i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-forest"></i>{{ __('home.talk_quote') }}
                    </p>
                </blockquote>
            </figure>

            <div class="rounded-[1.5rem] border border-white/20 bg-forest-dark p-6">
                <span class="badge bg-white/15 text-white"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i>{{ __('home.level1_badge') }}</span>
                <h3 class="mt-3 text-xl text-white">{{ __('home.level1_title', ['name' => config('aisa.assistant_name')]) }}</h3>
                <p class="mt-1.5 text-white/90">{{ __('home.level1_body') }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Privacy and choice. --}}
<section class="container-page py-16" aria-labelledby="trust-heading">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.trust_eyebrow') }}</p>
        <h2 id="trust-heading" class="mt-2 text-3xl leading-tight sm:text-4xl">{{ __('home.trust_heading') }}</h2>
    </div>
    <ul class="mt-10 grid gap-6 md:grid-cols-3">
        @foreach ([['user-round', 'trust_account'], ['shield-check', 'trust_private'], ['heart-handshake', 'trust_choice']] as [$icon, $key])
            <li class="card">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-sage-light text-forest">
                    <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                </span>
                <h3 class="mt-4 text-xl">{{ __("home.{$key}_title") }}</h3>
                <p class="mt-2 text-ink-soft">{{ __("home.{$key}_body") }}</p>
            </li>
        @endforeach
    </ul>
</section>

{{-- Latest articles. --}}
@if ($articles->isNotEmpty())
    <section class="border-y border-line bg-sand/50" aria-labelledby="articles-heading">
        <div class="container-page py-16">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold tracking-wide text-forest uppercase">{{ __('home.articles_eyebrow') }}</p>
                    <h2 id="articles-heading" class="mt-2 text-3xl leading-tight sm:text-4xl">{{ __('articles.heading') }}</h2>
                    <p class="mt-3 text-ink-soft">{{ __('home.articles_body') }}</p>
                </div>
                <a href="{{ route('articles.index') }}" class="btn-secondary shrink-0 self-start sm:self-auto">
                    {{ __('articles.view_all') }}<i data-lucide="arrow-right" class="h-5 w-5"></i>
                </a>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-article-card :article="$article" />
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="container-page pt-14">
    <div class="reading alert-danger">
        <i data-lucide="life-buoy" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
        <div>
            <p class="font-semibold">{{ __('home.not_emergency_title') }}</p>
            <p class="mt-1">{{ __('home.not_emergency_body') }}</p>
            <a href="{{ route('get-help') }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-clay">{{ __('ui.get_help_now') }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a>
        </div>
    </div>
</section>
@endsection
