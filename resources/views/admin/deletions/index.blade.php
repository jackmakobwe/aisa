@extends('layouts.admin')

@section('title', 'Deletion requests')

@php($tz = config('aisa.display_timezone'))

@section('content')
<h1 class="text-3xl">Deletion requests</h1>
<p class="mt-1 max-w-3xl text-ink-muted">Requests made by people using their code. Completing a request permanently deletes the conversation, its AI analyses, notes and safety flags. Only the reference and who processed it are kept.</p>

<div class="card mt-6 overflow-x-auto p-0">
    <table class="table-admin min-w-[720px]">
        <thead><tr><th scope="col">Reference</th><th scope="col">Requested</th><th scope="col">Notes</th><th scope="col">Action</th></tr></thead>
        <tbody>
            @forelse ($pending as $request)
                <tr>
                    <td>
                        @if ($request->ticket)
                            <a href="{{ route('admin.conversations.show', $request->ticket) }}" class="font-mono font-semibold">{{ $request->ticket_reference }}</a>
                        @else
                            <span class="font-mono">{{ $request->ticket_reference }}</span>
                        @endif
                    </td>
                    <td>{{ $request->requested_at->timezone($tz)->format('j M Y, H:i') }}</td>
                    <td class="text-sm">
                        @if ($request->ticket?->safety_flagged)
                            <span class="badge-clay">Has an open safety flag</span>
                            <p class="mt-1 text-xs text-ink-muted">Check whether there is a legal duty to keep this record before deleting.</p>
                        @endif
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.deletions.complete', $request) }}" data-confirm="Permanently delete conversation {{ $request->ticket_reference }}?">
                                @csrf
                                <button type="submit" class="btn-danger btn-sm">Delete conversation</button>
                            </form>
                            <details>
                                <summary class="btn-quiet btn-sm cursor-pointer list-none">Decline…</summary>
                                <form method="POST" action="{{ route('admin.deletions.reject', $request) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input name="note" type="text" required maxlength="255" placeholder="Reason (required)" class="field py-1.5 text-sm" aria-label="Reason for declining">
                                    <button type="submit" class="btn-secondary btn-sm">Decline</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-ink-muted">No pending requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="mt-10 text-xl">Recently processed</h2>
<ul class="card mt-4 divide-y divide-line p-0 text-sm">
    @forelse ($recent as $request)
        <li class="flex flex-wrap gap-3 px-5 py-3">
            <span class="font-mono">{{ $request->ticket_reference }}</span>
            <x-admin.badge type="status" :value="$request->status" />
            <span class="text-ink-muted">{{ $request->processor?->name }} · {{ $request->completed_at?->timezone($tz)->format('j M Y, H:i') }}</span>
            @if ($request->note)<span class="text-ink-soft">{{ $request->note }}</span>@endif
        </li>
    @empty
        <li class="px-5 py-6 text-ink-muted">None yet.</li>
    @endforelse
</ul>
@endsection
