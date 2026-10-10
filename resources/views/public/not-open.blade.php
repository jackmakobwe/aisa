@extends('layouts.public')

@section('title', __('pages.not_open_title'))

@section('content')
<div class="container-page py-16">
    <div class="reading text-center">
        <h1 class="text-4xl">{{ __('pages.not_open_title') }}</h1>
        <p class="mt-4 text-lg text-ink-soft">{{ __('pages.not_open_body') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('get-help') }}" class="btn-primary">{{ __('ui.get_help_now') }}</a>
            <a href="{{ route('about') }}" class="btn-secondary">{{ __('ui.nav_about') }}</a>
        </div>
    </div>
</div>
@endsection
