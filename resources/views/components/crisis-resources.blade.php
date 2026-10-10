@props(['resources', 'compact' => false])

{{-- Verified contacts only. When none are verified yet, give general guidance without inventing numbers. --}}
@if ($resources->isEmpty())
    <p class="text-ink-soft">{{ __('help_page.no_verified') }}</p>
@else
    <ul class="{{ $compact ? 'space-y-2' : 'grid gap-3 sm:grid-cols-2' }}">
        @foreach ($resources as $resource)
            <li class="{{ $compact ? '' : 'rounded-xl border border-line bg-white p-4' }}">
                <p class="font-semibold text-ink">{{ $resource->name }}</p>
                @if ($resource->phone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $resource->phone) }}" class="mt-1 inline-flex items-center gap-1.5 text-lg font-semibold">
                        <i data-lucide="phone" class="h-4 w-4"></i>{{ $resource->phone }}
                    </a>
                @endif
                @if ($resource->url)
                    <a href="{{ $resource->url }}" class="mt-1 flex items-center gap-1.5 text-sm" rel="noopener noreferrer" target="_blank">
                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i>{{ parse_url($resource->url, PHP_URL_HOST) }}
                    </a>
                @endif
                @if (! $compact && ($description = $resource->description(app()->getLocale())))
                    <p class="mt-1.5 text-sm text-ink-muted">{{ $description }}</p>
                @endif
                @if (! $compact && $resource->hours)
                    <p class="mt-1 text-xs text-ink-muted">{{ $resource->hours }}</p>
                @endif
            </li>
        @endforeach
    </ul>
@endif
