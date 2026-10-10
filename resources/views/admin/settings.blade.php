@extends('layouts.admin')

@section('title', 'Settings & launch')

@section('content')
<h1 class="text-3xl">Settings & launch checklist</h1>

<section class="card mt-8" aria-labelledby="checks-heading">
    <h2 id="checks-heading" class="text-xl">Launch checklist</h2>
    <p class="mt-1 text-sm text-ink-muted">In production, new conversations stay closed until every item passes. Run <code>php artisan aisa:launch-check</code> for the same list.</p>
    <ul class="mt-4 space-y-1.5 text-sm">
        @foreach ($checks as $check)
            <li class="flex gap-2">
                @if ($check['ok'])
                    <i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-forest"></i><span class="sr-only">Done:</span>
                @else
                    <i data-lucide="x" class="mt-0.5 h-4 w-4 shrink-0 text-clay"></i><span class="sr-only">Not done:</span>
                @endif
                <span @class(['text-ink-muted' => $check['ok']])>{{ $check['label'] }}</span>
            </li>
        @endforeach
    </ul>
</section>

<form method="POST" action="{{ route('admin.settings.update') }}" class="mt-8 space-y-8">
    @csrf
    <section class="card" aria-labelledby="approvals-heading">
        <h2 id="approvals-heading" class="text-xl">Approvals</h2>
        <p class="mt-1 text-sm text-ink-muted">Tick only when the review has actually happened. Every change is recorded in the audit log with your name.</p>
        <div class="mt-4 space-y-3">
            @foreach ($approvals as $key => $label)
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="approvals[{{ str_replace('.', '_', $key) }}]" value="1" class="mt-0.5 h-4 w-4 accent-forest" @checked($settings[$key])>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </section>

    <section class="card" aria-labelledby="capacity-heading">
        <h2 id="capacity-heading" class="text-xl">Capacity</h2>
        <label class="mt-3 flex items-start gap-3 text-sm">
            <input type="hidden" name="human_requests_open" value="0">
            <input type="checkbox" name="human_requests_open" value="1" class="mt-0.5 h-4 w-4 accent-forest" @checked($settings['human_requests_open'])>
            <span>Accept new "Talk to Aisa" requests. Untick to pause when there are more requests than can be answered well; people are told honestly, and safety-flagged conversations can still ask.</span>
        </label>
        <div class="mt-4 max-w-md">
            <label for="backup_responder_name" class="field-label text-sm">Named backup responder</label>
            <input id="backup_responder_name" name="backup_responder_name" type="text" maxlength="120" class="field py-2" value="{{ $settings['backup_responder_name'] }}">
        </div>
    </section>

    <section class="card" aria-labelledby="about-heading">
        <h2 id="about-heading" class="text-xl">About page</h2>
        <p class="mt-1 text-sm text-ink-muted">Shown on the public About page. Leave empty until Aisa has approved the wording. Do not add qualifications that have not been confirmed.</p>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @foreach (['about_bio_en' => 'Short introduction (English)', 'about_bio_sw' => 'Short introduction (Kiswahili)', 'about_review_standard_en' => 'How replies are reviewed (English)', 'about_review_standard_sw' => 'How replies are reviewed (Kiswahili)'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="field-label text-sm">{{ $label }}</label>
                    <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="2000" class="field text-sm">{{ $settings[str_replace('about_', 'about.', $field)] }}</textarea>
                </div>
            @endforeach
        </div>
    </section>

    <button type="submit" class="btn-primary">Save settings</button>
</form>

<section class="card mt-8" aria-labelledby="config-heading">
    <h2 id="config-heading" class="text-xl">Configuration (read-only)</h2>
    <p class="mt-1 text-sm text-ink-muted">Set in the server's <code>.env</code> file. See the README.</p>
    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[14rem_1fr]">
        @foreach ($config as $label => $value)
            <dt class="text-ink-muted">{{ $label }}</dt><dd class="font-mono">{{ $value }}</dd>
        @endforeach
    </dl>
</section>
@endsection
