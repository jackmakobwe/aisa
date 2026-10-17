@props(['contact'])

{{-- One service as a row: name with its number beside it, then a short line of details.
     Map links are plain links: Google is contacted only if the person taps one. --}}
@php($maps = $contact->mapsUrl())
@php($description = $contact->description(app()->getLocale()))
<li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
    <div class="min-w-0">
        <p class="font-semibold text-ink">
            {{ $contact->name }}
            @unless ($contact->is_verified)
                <span class="badge-amber ml-1 align-middle">{{ __('help_page.not_verified') }}</span>
            @endunless
        </p>
        @if ($contact->address || $description || $contact->hours)
            <p class="mt-0.5 text-sm text-ink-muted">{{ collect([$contact->address, $description, $contact->hours])->filter()->implode(' · ') }}</p>
        @endif
    </div>
    <div class="flex shrink-0 flex-wrap items-center gap-2">
        @if ($contact->phone)
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact->phone) }}" class="btn-primary btn-sm whitespace-nowrap" aria-label="{{ __('help_page.call') }} {{ $contact->name }}: {{ $contact->phone }}">
                <i data-lucide="phone" class="h-4 w-4"></i>{{ $contact->phone }}
            </a>
        @endif
        @if ($contact->url)
            <a href="{{ $contact->url }}" rel="noopener noreferrer" target="_blank" class="btn-secondary btn-sm">
                <i data-lucide="external-link" class="h-4 w-4"></i>{{ __('help_page.website') }}
            </a>
        @endif
        @if ($maps)
            <a href="{{ $maps }}" rel="noopener noreferrer" target="_blank" class="btn-secondary btn-sm" aria-label="{{ __('help_page.open_in_maps') }}: {{ $contact->name }}">
                <i data-lucide="map-pin" class="h-4 w-4"></i>{{ __('help_page.map_short') }}
            </a>
        @endif
    </div>
</li>
