@extends('layouts.public')

@section('title', __('pages.under18_title'))

@section('content')
<div class="container-page py-16">
    <div class="reading">
        <h1 class="text-4xl">{{ __('pages.under18_title') }}</h1>
        <p class="mt-4 text-lg text-ink-soft">{{ __('pages.under18_body') }}</p>
        <ul class="mt-6 list-disc space-y-2 pl-6 text-ink-soft">
            @foreach (__('pages.under18_points') as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>
        <a href="{{ route('get-help') }}" class="btn-primary mt-8">{{ __('ui.get_help_now') }}</a>
    </div>
</div>
@endsection
