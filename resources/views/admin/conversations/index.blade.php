@extends('layouts.admin')

@section('title', 'Conversations')

@section('content')
<h1 class="text-3xl">Conversations</h1>

<form method="GET" class="card mt-6 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-7" role="search">
    <div class="lg:col-span-2">
        <label for="q" class="field-label text-sm">Search</label>
        <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="field py-2" placeholder="Reference or words in summary">
    </div>
    @foreach ([
        'status' => ['' => 'Any status', 'awaiting_human' => 'Awaiting reply', 'open' => 'Open (AI)', 'answered' => 'Answered', 'closed' => 'Closed', 'flagged' => 'Safety flagged', 'blocked' => 'Blocked'],
        'language' => ['' => 'Any language', 'en' => 'English', 'sw' => 'Kiswahili', 'mixed' => 'Mixed'],
        'category' => ['' => 'Any category'] + collect(config('aisa.categories'))->mapWithKeys(fn ($c) => [$c => \App\Support\Format::label($c)])->all(),
        'urgency' => ['' => 'Any urgency'] + collect(config('aisa.urgency_levels'))->mapWithKeys(fn ($u) => [$u => \App\Support\Format::label($u)])->all(),
        'sort' => ['waiting' => 'Waiting longest', 'urgency' => 'Most urgent', 'newest' => 'Newest', 'activity' => 'Recent activity'],
    ] as $name => $options)
        <div>
            <label for="{{ $name }}" class="field-label text-sm">{{ ucfirst($name) }}</label>
            <select id="{{ $name }}" name="{{ $name }}" class="field py-2">
                @foreach ($options as $value => $label)
                    <option value="{{ $value }}" @selected(($filters[$name] ?? ($name === 'sort' ? 'waiting' : '')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-7">
        <button type="submit" class="btn-primary btn-sm"><i data-lucide="search" class="h-4 w-4"></i>Apply</button>
        <a href="{{ route('admin.conversations.index') }}" class="btn-quiet btn-sm">Clear</a>
        <span class="ml-auto text-sm text-ink-muted">{{ $tickets->total() }} conversation(s)</span>
    </div>
</form>

<div class="card mt-6 overflow-x-auto p-0">
    <table class="table-admin min-w-[960px]">
        <thead>
            <tr>
                <th scope="col">Reference</th>
                <th scope="col">Summary</th>
                <th scope="col">Category</th>
                <th scope="col">Language</th>
                <th scope="col">Urgency</th>
                <th scope="col">Status</th>
                <th scope="col">Started</th>
                <th scope="col">Waiting</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $ticket)
                @php($minutes = $ticket->waitingMinutes())
                <tr @class(['bg-clay-light/40' => $ticket->safety_flagged])>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('admin.conversations.show', $ticket) }}" class="font-mono font-semibold">{{ $ticket->reference }}</a>
                        @if ($ticket->safety_flagged)
                            <span class="badge-clay mt-1 flex w-fit"><i data-lucide="shield-alert" class="h-3 w-3"></i>Safety</span>
                        @endif
                        @if ($ticket->is_minor)
                            <span class="badge-amber mt-1 flex w-fit">Under 18</span>
                        @endif
                        @if ($ticket->isBlocked())
                            <span class="badge-neutral mt-1 flex w-fit">Blocked</span>
                        @endif
                    </td>
                    <td class="max-w-xs text-ink-soft">{{ \Illuminate\Support\Str::limit($ticket->latestTurnAnalysis?->summary, 110) ?: '—' }}</td>
                    <td>{{ \App\Support\Format::label($ticket->category) }}</td>
                    <td><x-admin.badge type="language" :value="$ticket->language" /></td>
                    <td><x-admin.badge type="urgency" :value="$ticket->urgency ?? 'low'" /></td>
                    <td>
                        <x-admin.badge type="status" :value="$ticket->status" />
                        @if ($ticket->help_mode === 'ai')<span class="mt-1 block text-xs text-ink-muted">AI only</span>@endif
                    </td>
                    <td class="whitespace-nowrap text-ink-muted">{{ $ticket->created_at->timezone(config('aisa.display_timezone'))->format('j M H:i') }}</td>
                    <td @class(['whitespace-nowrap', 'font-semibold text-clay' => $minutes !== null && $minutes > config('aisa.response_window_hours') * 60])>{{ \App\Support\Format::minutes($minutes) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-10 text-center text-ink-muted">No conversations match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $tickets->links() }}</div>
@endsection
