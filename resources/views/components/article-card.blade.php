@props(['article', 'headingLevel' => 3])

{{-- The whole card is clickable through the title link's ::after overlay, so
     there is a single link per card for screen readers and keyboard users. --}}
<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-[var(--radius-card)] border border-line bg-white shadow-[0_1px_2px_rgba(34,40,43,0.04)] transition-shadow duration-200 focus-within:shadow-lg hover:shadow-lg') }}>
    <x-article-cover :article="$article" />

    <div class="flex flex-1 flex-col p-5">
        <h{{ $headingLevel }} class="text-xl leading-snug">
            <a href="{{ route('articles.show', $article['slug']) }}" class="text-ink no-underline after:absolute after:inset-0 after:content-[''] group-hover:text-forest">
                {{ $article['title'] }}
            </a>
        </h{{ $headingLevel }}>
        <p class="mt-2 flex-1 text-[0.95rem] text-ink-soft">{{ $article['excerpt'] }}</p>
        <p class="mt-4 flex items-center justify-between gap-3 text-sm">
            <span class="flex items-center gap-1.5 text-ink-muted">
                <i data-lucide="clock" class="h-4 w-4"></i>{{ __('articles.minutes', ['count' => $article['minutes']]) }}
            </span>
            <span class="flex items-center gap-1 font-semibold text-forest" aria-hidden="true">
                {{ __('articles.read_more') }}<i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
            </span>
        </p>
    </div>
</article>
