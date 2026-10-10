@props(['data', 'labels' => []])

{{-- Horizontal single-series bars: one hue for magnitude, values in ink, never colour alone. --}}
@php($max = max(1, max($data ?: [0])))
@php($total = array_sum($data))
<ul class="space-y-2.5">
    @foreach ($data as $key => $value)
        <li class="grid grid-cols-[7.5rem_minmax(0,1fr)_3.5rem] items-center gap-3 text-sm" title="{{ $labels[$key] ?? \App\Support\Format::label($key) }}: {{ $value }}">
            <span class="truncate text-ink-soft">{{ $labels[$key] ?? \App\Support\Format::label($key) }}</span>
            <span class="h-3 rounded-r bg-sand" aria-hidden="true">
                <span class="block h-3 rounded-r bg-forest" style="width: {{ $value > 0 ? max(2, round(100 * $value / $max)) : 0 }}%"></span>
            </span>
            <span class="text-right font-semibold tabular-nums text-ink">{{ $value }}@if($total)<span class="ml-1 text-xs font-normal text-ink-muted">{{ round(100 * $value / $total) }}%</span>@endif</span>
        </li>
    @endforeach
</ul>
