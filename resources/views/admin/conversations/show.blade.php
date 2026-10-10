@extends('layouts.admin')

@section('title', $ticket->reference)

@php
    $tz = config('aisa.display_timezone');
    $openFlags = $ticket->safetyFlags->filter->isOpen();
    $sensitive = $ticket->safety_flagged || $ticket->is_minor || ($draft?->risk_flag ?? false);
@endphp

@section('content')
<a href="{{ route('admin.conversations.index') }}" class="inline-flex items-center gap-1 text-sm"><i data-lucide="arrow-left" class="h-4 w-4"></i>All conversations</a>

<div class="mt-3 flex flex-wrap items-center gap-3">
    <h1 class="font-mono text-3xl">{{ $ticket->reference }}</h1>
    <x-admin.badge type="status" :value="$ticket->status" />
    <x-admin.badge type="urgency" :value="$ticket->urgency ?? 'low'" />
    <x-admin.badge type="language" :value="$ticket->language" />
    @if ($ticket->is_minor)<span class="badge-amber">Under 18</span>@endif
    @if ($ticket->isBlocked())<span class="badge-neutral">Blocked</span>@endif
    @if ($ticket->deletionRequests->where('status', 'pending')->isNotEmpty())<span class="badge-clay">Deletion requested</span>@endif
</div>
<p class="mt-2 text-sm text-ink-muted">
    Started {{ $ticket->created_at->timezone($tz)->format('j M Y, H:i') }}
    · {{ $ticket->help_mode === 'human' ? 'Asked for Talk to Aisa '.$ticket->human_requested_at?->timezone($tz)->format('j M, H:i') : ($ticket->help_mode === 'ai' ? 'Chose AI guidance' : 'Has not chosen help yet') }}
    · {{ $ticket->hasCode() ? 'Has a code' : 'No code issued' }}
    @if ($ticket->display_name) · Calls themselves "{{ $ticket->display_name }}" @endif
    @if ($ticket->rating) · Rated reply {{ $ticket->rating }}/5 @endif
</p>

@if ($openFlags->isNotEmpty())
    <div class="alert-danger mt-6" role="alert">
        <i data-lucide="shield-alert" class="mt-0.5 h-5 w-5 shrink-0 text-clay"></i>
        <div>
            <p class="font-semibold">Open safety flag: {{ $openFlags->first()->level }} — {{ collect($openFlags->first()->reasons)->map(fn ($r) => \App\Support\Format::label($r))->implode(', ') }}</p>
            <p class="mt-1 text-sm">Raised {{ \App\Support\Format::minutes((int) $openFlags->first()->raised_at->diffInMinutes(now())) }} ago by {{ $openFlags->first()->source }}. Review the whole conversation, reply personally with stabilising language and help contacts, then resolve the flag in the <a href="{{ route('admin.safety.index') }}">Safety panel</a>.</p>
        </div>
    </div>
@endif

<div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
    {{-- Conversation and reply --}}
    <div class="min-w-0 space-y-8">
        <section class="card" aria-labelledby="thread-heading">
            <h2 id="thread-heading" class="text-xl">Conversation</h2>
            <ol class="mt-5 space-y-5">
                @foreach ($ticket->messages as $message)
                    @if ($message->sender === 'system')
                        <li class="text-center text-xs text-ink-muted">— Safety resources were shown to the person · {{ $message->created_at->timezone($tz)->format('j M H:i') }} —</li>
                    @else
                        <li @class(['flex flex-col', 'items-start' => $message->sender === 'user', 'items-end' => $message->sender !== 'user'])>
                            <span class="mb-1 text-xs font-semibold text-ink-muted">
                                @if ($message->sender === 'user') Person
                                @elseif ($message->sender === 'ai') AI assistant @if($message->is_mock)<span class="badge-amber">mock</span>@endif
                                @else Human reply · {{ $message->author?->name ?? 'former staff' }} @endif
                                · {{ $message->created_at->timezone($tz)->format('j M H:i') }}
                            </span>
                            <div @class([
                                'max-w-[85%] whitespace-pre-line break-words rounded-2xl px-4 py-3',
                                'border border-line bg-sand text-ink' => $message->sender === 'user',
                                'border border-sky/20 bg-sky-light text-ink' => $message->sender === 'ai',
                                'border border-forest/30 bg-mint text-ink' => $message->sender === 'human',
                            ])>{{ $message->body }}</div>
                        </li>
                    @endif
                @endforeach
            </ol>
        </section>

        <section class="card" aria-labelledby="reply-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="reply-heading" class="text-xl">Reply as Aisa</h2>
                <form method="POST" action="{{ route('admin.conversations.draft', $ticket) }}">
                    @csrf
                    <button type="submit" class="btn-quiet btn-sm"><i data-lucide="sparkles" class="h-4 w-4"></i>{{ $draft ? 'New AI draft' : 'Ask AI for a draft' }}</button>
                </form>
            </div>

            @if ($sensitive)
                <div class="alert-warning mt-4 text-sm">
                    <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber"></i>
                    <p><strong>Write this reply yourself.</strong> This conversation involves a safety concern, a person under 18, or a topic the AI marked as sensitive. Use calm, non-judgemental language, include help contacts, and do not lightly edit an AI draft.</p>
                </div>
            @endif

            @if (! $ticket->hasCode())
                <div class="alert-info mt-4 text-sm">
                    <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-sky"></i>
                    <p>This person has no code yet, so they can only see a reply if their browser session is still open.</p>
                </div>
            @endif

            @if ($draft)
                <div class="mt-4 rounded-xl border border-sky/30 bg-sky-light p-4 text-sm">
                    <p class="flex items-center gap-1.5 font-semibold text-sky"><i data-lucide="sparkles" class="h-4 w-4"></i>AI draft — review, edit or rewrite before sending</p>
                    @if ($draft->summary)<p class="mt-1 text-ink-soft">Note from the AI: {{ $draft->summary }}</p>@endif
                    <p class="mt-1 text-xs text-ink-muted">{{ $draft->provider }} · {{ $draft->model }} · prompt {{ $draft->prompt_version }} · {{ $draft->created_at->timezone($tz)->format('j M H:i') }}</p>
                </div>
            @elseif ($draftFailed)
                <p class="mt-4 text-sm text-ink-muted">The last AI draft attempt failed ({{ $draftFailed }}). Please write the reply yourself.</p>
            @endif

            <form method="POST" action="{{ route('admin.conversations.reply', $ticket) }}" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="draft_id" value="{{ $draft?->id }}">
                @if ($savedResponses->isNotEmpty())
                    <div x-data="savedResponses" data-target="reply-body">
                        <label for="saved" class="field-label text-sm">Insert a saved response</label>
                        <select id="saved" class="field py-2" x-on:change="insert">
                            <option value="">Choose…</option>
                            @foreach ($savedResponses as $saved)
                                <option value="{{ $saved->id }}" data-body="{{ $saved->body }}">[{{ strtoupper($saved->language) }}] {{ $saved->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="reply-body" class="field-label">Your reply</label>
                    <textarea id="reply-body" name="body" rows="12" required maxlength="6000" class="field">{{ old('body', $sensitive ? '' : $draft?->draft_reply) }}</textarea>
                    @if ($sensitive && $draft)
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-forest">Show the AI draft for reference</summary>
                            <p class="mt-2 whitespace-pre-line rounded-lg bg-sand p-3 text-ink-soft">{{ $draft->draft_reply }}</p>
                        </details>
                    @endif
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-line-strong bg-ivory p-3">
                    <input type="checkbox" name="approved" value="1" class="mt-1 h-5 w-5 shrink-0 accent-forest" required>
                    <span class="text-sm"><strong>I have read this reply in full and approve it.</strong> It will be shown to the person as a personal reply from Aisa.</span>
                </label>
                <button type="submit" class="btn-primary"><i data-lucide="send" class="h-4 w-4"></i>Send reply</button>
            </form>
        </section>
    </div>

    {{-- Side column --}}
    <aside class="space-y-6">
        <section class="card p-5">
            <h2 class="text-lg">AI analysis</h2>
            @if ($analysis)
                <p class="mt-2 text-sm text-ink-soft">{{ $analysis->summary ?: 'No summary.' }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-ink-muted">Suggested topic</dt><dd>{{ \App\Support\Format::label($analysis->category) }}</dd>
                    <dt class="text-ink-muted">Suggested urgency</dt><dd>{{ \App\Support\Format::label($analysis->urgency) }}</dd>
                    <dt class="text-ink-muted">Model risk flag</dt><dd>{{ $analysis->risk_flag ? 'Yes: '.collect($analysis->risk_types)->implode(', ') : 'No' }}</dd>
                </dl>
                <p class="mt-2 text-xs text-ink-muted">Suggestions only · {{ $analysis->provider }} {{ $analysis->model }} · prompt {{ $analysis->prompt_version }}</p>
            @else
                <p class="mt-2 text-sm text-ink-muted">No AI analysis yet.</p>
            @endif

            <form method="POST" action="{{ route('admin.conversations.classify', $ticket) }}" class="mt-4 grid grid-cols-2 gap-2 border-t border-line pt-4">
                @csrf
                <label class="text-xs text-ink-muted">Category
                    <select name="category" class="field mt-1 px-2 py-1.5 text-sm">
                        @foreach (config('aisa.categories') as $c)<option value="{{ $c }}" @selected($ticket->category === $c)>{{ \App\Support\Format::label($c) }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs text-ink-muted">Urgency
                    <select name="urgency" class="field mt-1 px-2 py-1.5 text-sm">
                        @foreach (config('aisa.urgency_levels') as $u)<option value="{{ $u }}" @selected($ticket->urgency === $u)>{{ \App\Support\Format::label($u) }}</option>@endforeach
                    </select>
                </label>
                <button type="submit" class="btn-secondary btn-sm col-span-2">Confirm classification</button>
            </form>
        </section>

        <section class="card p-5">
            <h2 class="text-lg">Status</h2>
            <form method="POST" action="{{ route('admin.conversations.status', $ticket) }}" class="mt-3 flex flex-wrap gap-2">
                @csrf
                @foreach (['answered' => 'Mark answered', 'awaiting_human' => 'Back to queue', 'closed' => 'Close'] as $value => $label)
                    @if ($ticket->status !== $value)
                        <button type="submit" name="status" value="{{ $value }}" class="btn-secondary btn-sm">{{ $label }}</button>
                    @endif
                @endforeach
            </form>
        </section>

        <section class="card p-5">
            <h2 class="flex items-center gap-2 text-lg"><i data-lucide="notebook-pen" class="h-4 w-4"></i>Private notes</h2>
            <p class="text-xs text-ink-muted">Never shown to the person.</p>
            <form method="POST" action="{{ route('admin.conversations.notes', $ticket) }}" class="mt-3 space-y-2">
                @csrf
                <label for="note" class="sr-only">New note</label>
                <textarea id="note" name="note" rows="3" required maxlength="3000" class="field text-sm"></textarea>
                <button type="submit" class="btn-secondary btn-sm">Add note</button>
            </form>
            <ul class="mt-4 space-y-3">
                @foreach ($ticket->notes as $note)
                    <li class="rounded-lg bg-sand p-3 text-sm">
                        <p class="whitespace-pre-line">{{ $note->body }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ $note->author?->name ?? 'former staff' }} · {{ $note->created_at->timezone($tz)->format('j M H:i') }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card p-5">
            <h2 class="flex items-center gap-2 text-lg"><i data-lucide="shield-alert" class="h-4 w-4"></i>Safety history</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($ticket->safetyFlags as $flag)
                    <li class="rounded-lg border border-line p-3">
                        <div class="flex flex-wrap items-center gap-2"><x-admin.badge type="level" :value="$flag->level" /><x-admin.badge type="status" :value="$flag->status" /><span class="text-xs text-ink-muted">{{ $flag->source }}</span></div>
                        <p class="mt-1">{{ collect($flag->reasons)->map(fn ($r) => \App\Support\Format::label($r))->implode(', ') }}</p>
                        <p class="text-xs text-ink-muted">Raised {{ $flag->raised_at->timezone($tz)->format('j M H:i') }}@if($flag->reviewed_at) · resolved by {{ $flag->reviewer?->name }} {{ $flag->reviewed_at->timezone($tz)->format('j M H:i') }}@endif</p>
                        @if ($flag->outcome)<p class="mt-1 text-ink-soft">{{ $flag->outcome }}</p>@endif
                    </li>
                @empty
                    <li class="text-ink-muted">No flags.</li>
                @endforelse
            </ul>
            <details class="mt-4">
                <summary class="cursor-pointer text-sm text-forest">Raise a flag manually</summary>
                <form method="POST" action="{{ route('admin.conversations.flag', $ticket) }}" class="mt-3 space-y-2 text-sm">
                    @csrf
                    @foreach (\App\Services\Safety\SafetyAssessment::TYPES as $type)
                        <label class="flex items-center gap-2"><input type="checkbox" name="types[]" value="{{ $type }}" class="accent-clay">{{ \App\Support\Format::label($type) }}</label>
                    @endforeach
                    <button type="submit" class="btn-danger btn-sm">Raise flag</button>
                </form>
            </details>
        </section>

        <section class="card p-5">
            <h2 class="text-lg">Abuse and data</h2>
            <form method="POST" action="{{ route('admin.conversations.block', $ticket) }}" class="mt-3 space-y-2" data-confirm="{{ $ticket->isBlocked() ? 'Unblock this code?' : 'Block this code? The person will no longer be able to open the conversation.' }}">
                @csrf
                @if ($ticket->isBlocked())
                    <input type="hidden" name="action" value="unblock">
                    <button type="submit" class="btn-secondary btn-sm">Unblock code</button>
                @else
                    <input type="hidden" name="action" value="block">
                    <label for="block-reason" class="text-xs text-ink-muted">Reason (for the audit log)</label>
                    <input id="block-reason" name="reason" type="text" maxlength="200" class="field py-2 text-sm" required>
                    <button type="submit" class="btn-secondary btn-sm">Block code</button>
                @endif
            </form>

            @can('admin')
                <a href="{{ route('admin.conversations.export', $ticket) }}" class="btn-quiet btn-sm mt-4"><i data-lucide="file-text" class="h-4 w-4"></i>Export as JSON</a>
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm text-clay">Delete this conversation</summary>
                    <form method="POST" action="{{ route('admin.conversations.destroy', $ticket) }}" class="mt-3 space-y-2" data-confirm="Permanently delete this conversation? This cannot be undone.">
                        @csrf
                        <label class="block text-xs text-ink-muted">Reason<input name="reason" type="text" maxlength="200" required class="field mt-1 py-2 text-sm"></label>
                        <label class="block text-xs text-ink-muted">Type {{ $ticket->reference }} to confirm<input name="confirm_reference" type="text" required class="field mt-1 py-2 font-mono text-sm"></label>
                        <button type="submit" class="btn-danger btn-sm">Delete permanently</button>
                    </form>
                </details>
            @endcan
        </section>

        <section class="card p-5">
            <h2 class="text-lg">Access history</h2>
            <ul class="mt-3 space-y-1.5 text-xs text-ink-muted">
                @foreach ($auditTrail as $log)
                    <li>{{ $log->created_at->timezone($tz)->format('j M H:i') }} · {{ $log->actorLabel() }} · {{ $log->action }}</li>
                @endforeach
            </ul>
        </section>
    </aside>
</div>
@endsection
