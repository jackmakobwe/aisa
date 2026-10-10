<div {{ $attributes->merge(['class' => 'items-center gap-1 text-sm']) }} role="group" aria-label="{{ __('ui.language') }}">
    @foreach (['en' => 'EN', 'sw' => 'SW'] as $code => $label)
        @if (app()->getLocale() === $code)
            <span class="rounded-md bg-ink px-2 py-1 font-semibold text-white" aria-current="true" lang="{{ $code }}">
                {{ $label }} <span class="sr-only">{{ $code === 'en' ? 'English' : 'Kiswahili' }}</span>
            </span>
        @else
            <a href="{{ route('locale', $code) }}" class="rounded-md px-2 py-1 font-semibold text-ink-soft no-underline hover:bg-sand" lang="{{ $code }}" hreflang="{{ $code }}">
                {{ $label }} <span class="sr-only">{{ $code === 'en' ? 'English' : 'Kiswahili' }}</span>
            </a>
        @endif
    @endforeach
</div>
