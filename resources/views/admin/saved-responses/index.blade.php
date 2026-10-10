@extends('layouts.admin')

@section('title', 'Saved responses')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-3xl">Saved responses</h1>
        <p class="mt-1 text-ink-muted">Reusable passages in English and Kiswahili to insert into replies.</p>
    </div>
    <a href="{{ route('admin.saved-responses.create') }}" class="btn-primary btn-sm">Add saved response</a>
</div>

<div class="mt-6 grid gap-4 md:grid-cols-2">
    @forelse ($responses as $response)
        <article class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-sans text-base font-semibold">{{ $response->title }}</h2>
                <span class="badge-neutral">{{ strtoupper($response->language) }}</span>
            </div>
            @if ($response->category)<p class="text-xs text-ink-muted">{{ \App\Support\Format::label($response->category) }}</p>@endif
            <p class="mt-2 line-clamp-4 whitespace-pre-line text-sm text-ink-soft">{{ $response->body }}</p>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('admin.saved-responses.edit', $response) }}" class="btn-secondary btn-sm">Edit</a>
                <form method="POST" action="{{ route('admin.saved-responses.destroy', $response) }}" data-confirm="Delete this saved response?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-quiet btn-sm text-clay">Delete</button>
                </form>
            </div>
        </article>
    @empty
        <div class="card text-ink-muted md:col-span-2">No saved responses yet.</div>
    @endforelse
</div>
@endsection
