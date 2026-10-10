@extends('layouts.admin')

@section('title', 'Crisis contacts')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-3xl">Crisis & referral contacts</h1>
        <p class="mt-1 max-w-3xl text-ink-muted">Only verified contacts appear on Get help now. Verify each one yourself (call the number, check hours and languages) and re-check every {{ \App\Models\CrisisResource::REVIEW_AFTER_DAYS }} days. Editing a contact unpublishes it until it is verified again.</p>
    </div>
    <a href="{{ route('admin.resources.create') }}" class="btn-primary btn-sm">Add contact</a>
</div>

@foreach (\App\Models\CrisisResource::CATEGORIES as $category)
    <section class="mt-8" aria-labelledby="c-{{ $category }}">
        <h2 id="c-{{ $category }}" class="text-xl">{{ __("help_page.category_{$category}", [], 'en') }}</h2>
        <div class="mt-3 space-y-3">
            @forelse ($resources->get($category, collect()) as $resource)
                <article class="card flex flex-wrap items-start gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $resource->name }}</p>
                        <p class="text-sm text-ink-soft">{{ $resource->phone }} @if($resource->url) · {{ $resource->url }} @endif @if($resource->hours) · {{ $resource->hours }} @endif</p>
                        @if ($resource->is_verified)
                            <p @class(['mt-1 text-xs', 'font-semibold text-clay' => $resource->isDueForReview(), 'text-ink-muted' => ! $resource->isDueForReview()])>
                                Verified by {{ $resource->verifier?->name ?? 'former staff' }} on {{ $resource->verified_at->format('j M Y') }}@if($resource->isDueForReview()) — due for re-check @endif
                            </p>
                        @endif
                    </div>
                    @if ($resource->is_verified)
                        <span class="badge-forest"><i data-lucide="check" class="h-3 w-3"></i>Published</span>
                    @else
                        <span class="badge-amber">Not verified · hidden</span>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.resources.edit', $resource) }}" class="btn-secondary btn-sm">Edit</a>
                        <details>
                            <summary class="btn-secondary btn-sm cursor-pointer list-none">{{ $resource->is_verified ? 'Re-verify' : 'Verify' }}</summary>
                            <form method="POST" action="{{ route('admin.resources.verify', $resource) }}" class="mt-2 w-64 space-y-2 rounded-lg border border-line bg-white p-3 text-sm">
                                @csrf
                                <label class="flex items-start gap-2"><input type="checkbox" name="checked" value="1" required class="mt-0.5 accent-forest">I have personally checked that this contact works and the details are correct today.</label>
                                <button type="submit" class="btn-primary btn-sm">Confirm</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}" data-confirm="Remove this contact?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-quiet btn-sm text-clay">Remove</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="card p-4 text-sm text-ink-muted">No contacts yet.</p>
            @endforelse
        </div>
    </section>
@endforeach
@endsection
