@props(['type', 'value'])

{{-- Status, urgency and safety badges. Text always carries the meaning; colour only reinforces it. --}}
@php
    $class = match ($type) {
        'urgency' => match ($value) { 'critical' => 'badge-clay', 'high' => 'badge-amber', 'medium' => 'badge-sky', default => 'badge-neutral' },
        'level' => match ($value) { 'critical' => 'badge-clay', 'high' => 'badge-amber', default => 'badge-sky' },
        'status' => match ($value) { 'awaiting_human' => 'badge-amber', 'answered' => 'badge-forest', 'open' => 'badge-sky', 'in_review' => 'badge-sky', 'resolved' => 'badge-forest', default => 'badge-neutral' },
        default => 'badge-neutral',
    };
    $text = match (true) {
        $type === 'status' && $value === 'awaiting_human' => 'Awaiting reply',
        $type === 'language' => match ($value) { 'en' => 'English', 'sw' => 'Kiswahili', 'mixed' => 'Mixed', default => '—' },
        default => \App\Support\Format::label($value),
    };
@endphp
<span {{ $attributes->merge(['class' => $class]) }}>
    @if (in_array($type, ['level', 'urgency'], true) && in_array($value, ['critical', 'high'], true))
        <i data-lucide="alert-triangle" class="h-3 w-3"></i>
    @endif
    {{ $text }}
</span>
