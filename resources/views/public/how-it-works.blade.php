@extends('layouts.public')

@section('title', __('ui.nav_how'))

@section('content')
<div class="container-page py-12">
    <div class="reading">
        <h1 class="text-4xl">{{ __('how.heading') }}</h1>
        <p class="mt-3 text-lg text-ink-soft">{{ __('how.intro') }}</p>

        <ol class="mt-10 space-y-6">
            @foreach (__('how.steps', ['name' => config('aisa.assistant_name'), 'hours' => config('aisa.response_window_hours')]) as $i => $step)
                <li class="flex gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-forest font-semibold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h2 class="text-xl">{{ $step['title'] }}</h2>
                        <p class="mt-1 text-ink-soft">{{ $step['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="mt-12">
            <x-content-sections :sections="__('how.sections', ['hours' => config('aisa.response_window_hours'), 'days' => config('aisa.retention_days'), 'name' => config('aisa.assistant_name')])" />
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('home') }}" class="btn-primary">{{ __('how.cta') }}</a>
            <a href="{{ route('privacy') }}" class="btn-secondary">{{ __('ui.nav_privacy') }}</a>
        </div>
    </div>
</div>
@endsection
