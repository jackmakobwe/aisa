@extends('layouts.admin')

@section('title', 'Safety panel')

@php($tz = config('aisa.display_timezone'))

@section('content')
<h1 class="flex items-center gap-2 text-3xl"><i data-lucide="shield-alert" class="h-7 w-7 text-clay"></i>Safety panel</h1>
<p class="mt-1 max-w-3xl text-ink-muted">Flags are raised by the safety rules, the AI, the under-18 policy or a responder. Highest level first, then oldest. A flag is only resolved by a person, after reviewing the whole conversation.</p>

<section class="mt-8 space-y-4" aria-labelledby="open-heading">
    <h2 id="open-heading" class="text-xl">Open ({{ $open->count() }})</h2>
    @forelse ($open as $flag)
        @php($minutes = (int) $flag->raised_at->diffInMinutes(now()))
        <article @class(['card p-5', 'border-clay/50' => $flag->level === 'critical'])>
            <div class="flex flex-wrap items-center gap-3">
                <x-admin.badge type="level" :value="$flag->level" />
                <x-admin.badge type="status" :value="$flag->status" />
                <a href="{{ route('admin.conversations.show', $flag->ticket) }}" class="font-mono text-lg font-semibold">{{ $flag->ticket->reference }}</a>
                <span class="text-sm text-ink-muted">{{ collect($flag->reasons)->map(fn ($r) => \App\Support\Format::label($r))->implode(', ') }} · source: {{ $flag->source }}</span>
                <span @class(['ml-auto flex items-center gap-1 text-sm', 'font-semibold text-clay' => $minutes > 60, 'text-ink-muted' => $minutes <= 60])>
                    <i data-lucide="clock" class="h-4 w-4"></i>
                    <time datetime="{{ $flag->raised_at->toIso8601String() }}">{{ \App\Support\Format::minutes($minutes) }} since flagged</time>
                </span>
            </div>
            <p class="mt-2 text-sm text-ink-muted">
                Raised {{ $flag->raised_at->timezone($tz)->format('j M Y, H:i') }}
                · {{ $flag->ticket->hasCode() ? 'Person has a code' : 'No code: they can only see replies while their session is open' }}
                · {{ $flag->ticket->help_mode === 'human' ? 'Asked for Aisa' : 'Has not asked for Aisa' }}
                @if ($flag->assignee) · Being reviewed by {{ $flag->assignee->name }} @endif
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('admin.conversations.show', $flag->ticket) }}" class="btn-primary btn-sm">Open conversation</a>
                @unless ($flag->assigned_to === auth()->id())
                    <form method="POST" action="{{ route('admin.safety.assign', $flag) }}">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm">I am reviewing this</button>
                    </form>
                @endunless
            </div>

            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-semibold text-forest">Resolve this flag</summary>
                <form method="POST" action="{{ route('admin.safety.resolve', $flag) }}" class="mt-3 space-y-3">
                    @csrf
                    <label for="outcome-{{ $flag->id }}" class="field-label text-sm">What did you do, and why is it safe to close?</label>
                    <textarea id="outcome-{{ $flag->id }}" name="outcome" rows="3" required minlength="10" maxlength="2000" class="field text-sm"></textarea>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="reviewed_full" value="1" required class="mt-0.5 h-4 w-4 accent-forest">
                        I have reviewed the full conversation.
                    </label>
                    <button type="submit" class="btn-secondary btn-sm">Resolve and record</button>
                </form>
            </details>
        </article>
    @empty
        <div class="card text-ink-muted">No open safety flags.</div>
    @endforelse
</section>

<section class="mt-12" aria-labelledby="resolved-heading">
    <h2 id="resolved-heading" class="text-xl">Recently resolved</h2>
    <div class="card mt-4 overflow-x-auto p-0">
        <table class="table-admin min-w-[720px]">
            <thead><tr><th scope="col">Reference</th><th scope="col">Level</th><th scope="col">Reasons</th><th scope="col">Resolved by</th><th scope="col">Time to resolve</th><th scope="col">Outcome</th></tr></thead>
            <tbody>
                @forelse ($resolved as $flag)
                    <tr>
                        <td><a href="{{ route('admin.conversations.show', $flag->ticket) }}" class="font-mono">{{ $flag->ticket->reference }}</a></td>
                        <td><x-admin.badge type="level" :value="$flag->level" /></td>
                        <td>{{ collect($flag->reasons)->map(fn ($r) => \App\Support\Format::label($r))->implode(', ') }}</td>
                        <td>{{ $flag->reviewer?->name ?? '—' }}</td>
                        <td>{{ \App\Support\Format::minutes((int) $flag->raised_at->diffInMinutes($flag->reviewed_at)) }}</td>
                        <td class="max-w-sm text-ink-soft">{{ \Illuminate\Support\Str::limit($flag->outcome, 140) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-ink-muted">None yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
