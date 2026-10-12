@extends('layouts.admin')

@section('title', 'Analytics')

@php($maxDay = max(1, max($volume ?: [0])))

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl">Analytics</h1>
        <p class="mt-1 text-ink-muted">Counts from stored records only, for conversations started in the last {{ $days }} days. No personal content is shown.</p>
    </div>
    <nav class="flex gap-2" aria-label="Time range">
        @foreach ([7, 30, 90] as $range)
            <a href="{{ route('admin.analytics', ['days' => $range]) }}" @class(['btn-sm', 'btn-primary' => $days === $range, 'btn-secondary' => $days !== $range]) @if($days === $range) aria-current="true" @endif>{{ $range }} days</a>
        @endforeach
    </nav>
</div>

<dl class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ([
        ['Conversations', $total, null],
        ['Asked for Aisa', $humanRequests, $total ? round(100 * $humanRequests / $total).'% of conversations' : null],
        ['Median first reply', $responseMedian === null ? '—' : $responseMedian.' h', $responseCount ? $withinWindow.'% within '.$window.' h ('.$responseCount.' replies)' : 'No replies yet'],
        ['Came back with code', $returned, $codesIssued ? round(100 * $returned / $codesIssued).'% of '.$codesIssued.' codes issued' : 'No codes issued'],
        ['Safety flags raised', array_sum($flagLevels), $flagsOpen.' still open'],
        ['Median time to resolve a flag', $flagReviewMedian === null ? '—' : \App\Support\Format::minutes($flagReviewMedian), null],
        ['Average rating', $ratingAvg === null ? '—' : $ratingAvg.' / 5', $ratingCount.' rating(s)'],
    ] as [$label, $value, $note])
        <div class="card p-4">
            <dt class="text-sm text-ink-muted">{{ $label }}</dt>
            <dd class="mt-1 font-serif text-3xl font-semibold text-ink">{{ $value }}</dd>
            @if ($note)<dd class="mt-0.5 text-xs text-ink-muted">{{ $note }}</dd>@endif
        </div>
    @endforeach
</dl>

<section class="card mt-8" aria-labelledby="volume-heading">
    <h2 id="volume-heading" class="text-xl">New conversations per day</h2>
    @if ($total === 0)
        <p class="mt-4 text-ink-muted">No conversations in this period.</p>
    @else
        <div class="mt-6 flex h-48 items-end gap-0.5 border-b border-line-strong" aria-hidden="true">
            @foreach ($volume as $day => $count)
                <div class="group relative flex h-full flex-1 items-end" title="{{ \Carbon\Carbon::parse($day)->format('j M') }}: {{ $count }}">
                    <div class="w-full rounded-t-[4px] bg-forest group-hover:bg-forest-dark" style="height: {{ $count ? max(2, round(100 * $count / $maxDay)) : 0 }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-ink-muted" aria-hidden="true">
            <span>{{ \Carbon\Carbon::parse(array_key_first($volume))->format('j M') }}</span>
            <span>Peak: {{ $maxDay }} per day</span>
            <span>{{ \Carbon\Carbon::parse(array_key_last($volume))->format('j M') }}</span>
        </div>
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-forest">Show as table</summary>
            <table class="table-admin mt-2 max-w-sm">
                <thead><tr><th scope="col">Day</th><th scope="col">Conversations</th></tr></thead>
                <tbody>@foreach ($volume as $day => $count)<tr><td>{{ \Carbon\Carbon::parse($day)->format('D j M') }}</td><td>{{ $count }}</td></tr>@endforeach</tbody>
            </table>
        </details>
    @endif
</section>

<div class="mt-8 grid gap-8 lg:grid-cols-3">
    <section class="card" aria-labelledby="cat-heading">
        <h2 id="cat-heading" class="text-xl">Topics</h2>
        <p class="mb-4 text-xs text-ink-muted">AI-suggested or responder-confirmed category.</p>
        <x-admin.bar-list :data="$categories" />
        <h3 class="mt-6 text-base">Starting area</h3>
        <p class="mb-4 text-xs text-ink-muted">What people chose on the home page.</p>
        <x-admin.bar-list :data="$modules" :labels="collect(config('aisa.modules'))->mapWithKeys(fn ($m) => [$m => __('home.modules.'.$m.'.title', [], 'en')])->all() + ['none' => 'None chosen']" />
    </section>
    <section class="card" aria-labelledby="lang-heading">
        <h2 id="lang-heading" class="text-xl">Languages</h2>
        <p class="mb-4 text-xs text-ink-muted">Language people wrote in.</p>
        <x-admin.bar-list :data="$languages" :labels="['en' => 'English', 'sw' => 'Kiswahili', 'mixed' => 'Mixed']" />
    </section>
    <section class="card" aria-labelledby="flag-heading">
        <h2 id="flag-heading" class="text-xl">Safety flags by level</h2>
        <p class="mb-4 text-xs text-ink-muted">Every flag is reviewed by a person.</p>
        <x-admin.bar-list :data="$flagLevels" />
    </section>
</div>
@endsection
