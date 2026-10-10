@extends('layouts.admin')

@section('title', 'Overview')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl">Overview</h1>
        <p class="mt-1 text-ink-muted">Promised response window: {{ $windowHours }} hours.</p>
    </div>
    @unless ($humanOpen)
        <span class="badge-amber text-sm">New "Talk to Aisa" requests are paused</span>
    @endunless
</div>

@if ($failedAlerts > 0)
    <div class="alert-danger mt-6" role="alert">
        <i data-lucide="alert-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
        <p><strong>{{ $failedAlerts }} safety alert(s) failed to send in the last 7 days.</strong> Check the mail settings: responders may not be hearing about flagged conversations.</p>
    </div>
@endif

{{-- Overview strip --}}
<dl class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    @php
        $tiles = [
            ['Safety flags open', $counts['flagged'], route('admin.safety.index'), $counts['flagged'] > 0 ? 'border-clay/40 bg-clay-light' : ''],
            ['Awaiting reply', $counts['awaiting'], route('admin.conversations.index', ['status' => 'awaiting_human']), ''],
            ['New (24 h)', $counts['new'], route('admin.conversations.index', ['sort' => 'newest']), ''],
            ['Answered', $counts['answered'], route('admin.conversations.index', ['status' => 'answered']), ''],
            ['Deletion requests', $counts['deletions'], route('admin.deletions.index'), ''],
            ['Unread contact', $counts['contacts'], route('admin.contacts.index'), ''],
        ];
    @endphp
    @foreach ($tiles as [$label, $value, $href, $extra])
        {{-- The link is stretched over the whole tile, keeping valid dt/dd structure. --}}
        <div class="card relative p-4 hover:border-line-strong {{ $extra }}">
            <dt class="text-sm text-ink-muted"><a href="{{ $href }}" class="text-ink-muted no-underline after:absolute after:inset-0 after:content-['']">{{ $label }}</a></dt>
            <dd class="mt-1 font-serif text-3xl font-semibold text-ink">{{ $value }}</dd>
        </div>
    @endforeach
</dl>

<div class="mt-4 card flex flex-wrap items-center gap-x-8 gap-y-2 p-4 text-sm">
    <span><span class="text-ink-muted">Average first human reply (30 days):</span>
        <strong>{{ \App\Support\Format::minutes($avgResponseMinutes) }}</strong>
        @if ($responseSample) <span class="text-ink-muted">({{ $responseSample }} replies)</span> @else <span class="text-ink-muted">(no replies yet)</span> @endif
    </span>
</div>

<div class="mt-8 grid grid-cols-1 gap-8 xl:grid-cols-2">
    <section class="card min-w-0 p-0" aria-labelledby="flags-heading">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 id="flags-heading" class="flex items-center gap-2 text-xl"><i data-lucide="shield-alert" class="h-5 w-5 text-clay"></i>Safety flags</h2>
            <a href="{{ route('admin.safety.index') }}" class="text-sm">Open safety panel</a>
        </div>
        @forelse ($openFlags as $flag)
            <a href="{{ route('admin.conversations.show', $flag->ticket) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 text-ink no-underline last:border-0 hover:bg-ivory">
                <x-admin.badge type="level" :value="$flag->level" />
                <span class="font-mono text-sm font-semibold">{{ $flag->ticket->reference }}</span>
                <span class="min-w-0 truncate text-sm text-ink-muted">{{ collect($flag->reasons)->map(fn ($r) => \App\Support\Format::label($r))->implode(', ') }}</span>
                <span class="ml-auto shrink-0 text-sm text-ink-muted"><i data-lucide="clock" class="mr-1 inline h-3.5 w-3.5"></i>{{ \App\Support\Format::minutes((int) $flag->raised_at->diffInMinutes(now())) }}</span>
            </a>
        @empty
            <p class="px-5 py-6 text-ink-muted">No open safety flags.</p>
        @endforelse
    </section>

    <section class="card min-w-0 p-0" aria-labelledby="waiting-heading">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 id="waiting-heading" class="flex items-center gap-2 text-xl"><i data-lucide="inbox" class="h-5 w-5 text-forest"></i>Waiting longest</h2>
            <a href="{{ route('admin.conversations.index', ['status' => 'awaiting_human']) }}" class="text-sm">Open queue</a>
        </div>
        @forelse ($waiting as $ticket)
            @php($minutes = $ticket->waitingMinutes())
            <a href="{{ route('admin.conversations.show', $ticket) }}" class="flex items-center gap-3 border-b border-line px-5 py-3 text-ink no-underline last:border-0 hover:bg-ivory">
                <span class="font-mono text-sm font-semibold">{{ $ticket->reference }}</span>
                <span class="min-w-0 truncate text-sm text-ink-muted">{{ $ticket->latestTurnAnalysis?->summary ?: \App\Support\Format::label($ticket->category) }}</span>
                <span @class(['ml-auto shrink-0 text-sm', 'font-semibold text-clay' => $minutes !== null && $minutes > $windowHours * 60, 'text-ink-muted' => $minutes === null || $minutes <= $windowHours * 60])>{{ \App\Support\Format::minutes($minutes) }}</span>
            </a>
        @empty
            <p class="px-5 py-6 text-ink-muted">Nobody is waiting for a reply.</p>
        @endforelse
    </section>
</div>

@if ($blockers)
    <section class="card mt-8" aria-labelledby="launch-heading">
        <h2 id="launch-heading" class="flex items-center gap-2 text-xl"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber"></i>Not ready for public launch</h2>
        <p class="mt-1 text-sm text-ink-muted">These must be resolved before opening to the public. In production, new conversations stay closed until they are.</p>
        <ul class="mt-4 space-y-1.5 text-sm">
            @foreach ($blockers as $blocker)
                <li class="flex gap-2"><i data-lucide="x" class="mt-0.5 h-4 w-4 shrink-0 text-clay"></i>{{ $blocker['label'] }}</li>
            @endforeach
        </ul>
        @can('admin')
            <a href="{{ route('admin.settings') }}" class="btn-secondary btn-sm mt-4">Review launch checklist</a>
        @endcan
    </section>
@endif
@endsection
