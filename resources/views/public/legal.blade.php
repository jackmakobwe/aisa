@extends('layouts.public')

@section('title', $title)

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-4xl">{{ $title }}</h1>
        <div class="alert-warning mt-6" role="note">
            <i data-lucide="file-text" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
            <p>{{ __('legal.draft_notice') }}</p>
        </div>
        <p class="mt-6 text-lg text-ink-soft">{{ $intro }}</p>
        <div class="mt-6">
            <x-content-sections :sections="$sections" />
        </div>
        <p class="mt-10 text-sm text-ink-muted">{{ __('legal.last_updated', ['version' => config('aisa.consent_version')]) }}</p>
    </div>
</div>
@endsection
