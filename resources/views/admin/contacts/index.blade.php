@extends('layouts.admin')

@section('title', 'Contact & reports')

@section('content')
<h1 class="text-3xl">Contact & reports</h1>
<div class="mt-4 flex gap-2">
    <a href="{{ route('admin.contacts.index') }}" @class(['btn-sm', 'btn-primary' => $status === 'new', 'btn-secondary' => $status !== 'new'])>New</a>
    <a href="{{ route('admin.contacts.index', ['status' => 'handled']) }}" @class(['btn-sm', 'btn-primary' => $status === 'handled', 'btn-secondary' => $status !== 'handled'])>Handled</a>
</div>

<div class="mt-6 space-y-4">
    @forelse ($submissions as $submission)
        <article class="card p-5">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span @class(['badge-clay' => $submission->type === 'inappropriate', 'badge-neutral' => $submission->type !== 'inappropriate'])>{{ __("contact.type_{$submission->type}", [], 'en') }}</span>
                <span class="badge-neutral">{{ strtoupper($submission->locale) }}</span>
                <span class="text-ink-muted">{{ $submission->created_at->timezone(config('aisa.display_timezone'))->format('j M Y, H:i') }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line">{{ $submission->message }}</p>
            <p class="mt-2 text-sm text-ink-muted">Reply to: {{ $submission->contact ?: 'not given (anonymous)' }}</p>
            @if ($submission->status === 'new')
                <form method="POST" action="{{ route('admin.contacts.handled', $submission) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn-secondary btn-sm"><i data-lucide="check" class="h-4 w-4"></i>Mark handled</button>
                </form>
            @endif
        </article>
    @empty
        <div class="card text-ink-muted">Nothing here.</div>
    @endforelse
</div>
<div class="mt-6">{{ $submissions->links() }}</div>
@endsection
