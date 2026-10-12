@props(['article', 'large' => false])

{{-- Article cover: the article's photo when there is one, otherwise the topic
     colour and an icon. The topic badge is real text either way. Class names
     are written out in full so Tailwind finds them. --}}
@php
    $tones = [
        'therapy' => ['bg' => 'from-sage-light via-mint to-white', 'ring' => 'bg-forest/10', 'icon' => 'text-forest', 'badge' => 'text-forest-dark'],
        'girls' => ['bg' => 'from-rose-light via-white to-sand', 'ring' => 'bg-rose/10', 'icon' => 'text-rose', 'badge' => 'text-rose'],
        'wellbeing' => ['bg' => 'from-sky-light via-white to-mint', 'ring' => 'bg-sky/10', 'icon' => 'text-sky', 'badge' => 'text-sky'],
        'relationships' => ['bg' => 'from-plum-light via-white to-rose-light', 'ring' => 'bg-plum/10', 'icon' => 'text-plum', 'badge' => 'text-plum'],
        'family' => ['bg' => 'from-amber-light via-white to-sand', 'ring' => 'bg-amber/10', 'icon' => 'text-amber', 'badge' => 'text-amber'],
    ];
    $tone = $tones[$article['topic']] ?? $tones['therapy'];
    $photo = $article['photo'] ?? null;
@endphp

<div {{ $attributes->class(['relative aspect-[16/9] overflow-hidden', 'rounded-[var(--radius-card)]' => $large, 'bg-linear-to-br '.$tone['bg'] => ! $photo, 'bg-sand' => $photo]) }}>
    @if ($photo)
        <img src="{{ $large ? $photo['large'] : $photo['small'] }}"
             srcset="{{ $photo['small'] }} 640w, {{ $photo['large'] }} 1200w"
             sizes="{{ $large ? '(min-width: 768px) 720px, 100vw' : '(min-width: 1024px) 360px, (min-width: 640px) 50vw, 100vw' }}"
             alt="{{ $photo['alt'] }}" width="640" height="360"
             @if ($large) fetchpriority="high" @else loading="lazy" @endif decoding="async"
             class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]">
    @else
        <span class="absolute -top-10 -right-8 h-40 w-40 rounded-full {{ $tone['ring'] }}" aria-hidden="true"></span>
        <span class="absolute -bottom-12 -left-6 h-32 w-32 rounded-full {{ $tone['ring'] }}" aria-hidden="true"></span>

        <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
            <span class="flex items-center justify-center rounded-full bg-white/90 shadow-sm {{ $large ? 'h-24 w-24' : 'h-16 w-16' }}">
                <i data-lucide="{{ $article['icon'] }}" class="{{ $tone['icon'] }} {{ $large ? 'h-11 w-11' : 'h-8 w-8' }}"></i>
            </span>
        </span>
    @endif

    <span class="badge absolute top-3 left-3 bg-white/95 shadow-sm {{ $tone['badge'] }}">
        {{ __('articles.topics.'.$article['topic']) }}
    </span>
</div>
