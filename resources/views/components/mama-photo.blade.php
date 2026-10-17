@props(['key', 'photo', 'large' => false])

{{-- A Mama Care photo with its credit. CC BY-SA photos are credited with the
     author, licence link and a note that they were cropped. --}}
<figure {{ $attributes }}>
    <div class="relative aspect-[16/9] overflow-hidden bg-sand {{ $large ? 'rounded-[1.5rem] shadow-[0_20px_50px_-20px_rgba(34,40,43,0.35)]' : 'rounded-2xl' }}">
        <img src="{{ asset("images/mama/{$key}-".($large ? '1200' : '640').'.webp') }}"
             srcset="{{ asset("images/mama/{$key}-640.webp") }} 640w, {{ asset("images/mama/{$key}-1200.webp") }} 1200w"
             sizes="{{ $large ? '(min-width: 1024px) 560px, 100vw' : '(min-width: 768px) 720px, 100vw' }}"
             alt="{{ __("mama.photos.{$key}") }}" width="640" height="360"
             @if ($large) fetchpriority="high" @else loading="lazy" @endif decoding="async"
             class="absolute inset-0 h-full w-full object-cover">
    </div>
    <figcaption class="mt-1.5 text-xs text-ink-muted">
        {{ __('mama.photo_label') }}: <a href="{{ $photo['source'] }}" rel="noopener" class="text-ink-muted">{{ $photo['credit'] }}</a>@if ($photo['licence_url']), <a href="{{ $photo['licence_url'] }}" rel="license noopener" class="text-ink-muted">{{ $photo['licence'] }}</a>, {{ __('mama.photo_cropped') }}@else, {{ $photo['licence'] }}@endif
    </figcaption>
</figure>
