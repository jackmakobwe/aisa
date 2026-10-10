@extends('layouts.admin')

@section('title', 'Audit log')

@section('content')
<h1 class="text-3xl">Audit log</h1>
<p class="mt-1 text-ink-muted">Every sign-in, conversation view and administrative action. Entries contain identifiers only, never message content.</p>

<form method="GET" class="card mt-6 flex flex-wrap items-end gap-4 p-4">
    <div>
        <label for="user" class="field-label text-sm">Person</label>
        <select id="user" name="user" class="field py-2">
            <option value="">Everyone</option>
            @foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? null) == $u->id)>{{ $u->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="action" class="field-label text-sm">Action starts with</label>
        <input id="action" name="action" type="text" class="field py-2" placeholder="e.g. conversation." value="{{ $filters['action'] ?? '' }}">
    </div>
    <button type="submit" class="btn-primary btn-sm">Filter</button>
</form>

<div class="card mt-6 overflow-x-auto p-0">
    <table class="table-admin min-w-[860px]">
        <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Subject</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap">{{ $log->created_at->timezone(config('aisa.display_timezone'))->format('j M Y, H:i:s') }}</td>
                    <td>{{ $log->actorLabel() }}</td>
                    <td class="font-mono text-xs">{{ $log->action }}</td>
                    <td class="text-xs">
                        @if ($log->subject_type === 'Ticket' && ($t = \App\Models\Ticket::find($log->subject_id)))
                            <a href="{{ route('admin.conversations.show', $t) }}" class="font-mono">{{ $t->reference }}</a>
                        @else
                            {{ $log->subject_type }} {{ $log->subject_id }}
                        @endif
                    </td>
                    <td class="max-w-xs font-mono text-xs break-all text-ink-muted">{{ $log->metadata ? json_encode($log->metadata) : '' }}</td>
                    <td class="font-mono text-xs text-ink-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-ink-muted">No entries.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $logs->links() }}</div>
@endsection
