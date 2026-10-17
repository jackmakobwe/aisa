@extends('layouts.public')

@section('title', $article['title'])

@push('meta')
    <meta property="og:title" content="{{ $article['title'] }}">
    <meta property="og:description" content="{{ $article['excerpt'] }}">
@endpush

@section('content')
<article class="container-page py-10 sm:py-12">
    <div class="reading">
        <a href="{{ route('articles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold no-underline hover:underline">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>{{ __('articles.back') }}
        </a>

        <h1 class="mt-5 text-4xl leading-tight sm:text-[2.6rem]">{{ $article['title'] }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ $article['excerpt'] }}</p>
        <p class="mt-4 flex items-center gap-1.5 text-sm text-ink-muted">
            <i data-lucide="clock" class="h-4 w-4"></i>{{ __('articles.minutes', ['count' => $article['minutes']]) }}
        </p>

        <x-article-cover :article="$article" :large="true" class="mt-8" />
        @if ($article['photo'])
            <p class="mt-2 text-xs text-ink-muted">
                @php($photo = $article['photo'])
                {!! __('articles.photo_credit', [
                    'name' => '<a href="'.e($photo['source']).'" rel="noopener" target="_blank">'.e($photo['credit']).'</a>',
                    'licence' => $photo['licence_url']
                        ? '<a href="'.e($photo['licence_url']).'" rel="license noopener" target="_blank">'.e($photo['licence']).'</a>, '.e(__('articles.photo_cropped'))
                        : e($photo['licence']),
                ]) !!}
            </p>
        @endif

        @if (! config('articles.reviewed'))
            <p class="alert-warning mt-8">
                <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
                <span>{{ __('articles.draft_notice') }}</span>
            </p>
        @endif

        <div class="mt-4">
            <x-content-sections :sections="$article['sections']" />
        </div>

        <p class="mt-8 border-t border-line pt-6 text-sm text-ink-muted">{{ __('articles.general_info') }}</p>

        <aside class="mt-10 overflow-hidden rounded-[var(--radius-card)] bg-forest p-6 text-white sm:p-8" aria-labelledby="article-cta">
            <h2 id="article-cta" class="text-2xl text-white">{{ __('articles.cta_title') }}</h2>
            <p class="mt-2 text-white/85">{{ __('articles.cta_body') }}</p>
            <a href="{{ route('home') }}#start" class="btn mt-5 bg-white text-forest-dark hover:bg-sand focus-visible:outline-white">
                {{ __('articles.cta_button') }}<i data-lucide="arrow-right" class="h-5 w-5"></i>
            </a>
        </aside>

        <p class="alert-danger mt-6">
            <i data-lucide="life-buoy" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
            <span>
                {{ __('articles.danger_note') }}
                <a href="{{ route('get-help') }}" class="font-semibold text-clay">{{ __('ui.get_help_now') }}</a>
            </span>
        </p>
    </div>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-16 max-w-4xl" aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-2xl">{{ __('articles.related_heading') }}</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                @foreach ($related as $other)
                    <x-article-card :article="$other" />
                @endforeach
            </div>
        </section>
    @endif
</article>
@endsection
