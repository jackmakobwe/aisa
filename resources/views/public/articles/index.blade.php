@extends('layouts.public')

@section('title', __('articles.title'))

@section('content')
<section class="border-b border-line bg-linear-to-b from-sand/70 to-ivory">
    <div class="container-page py-12 sm:py-16">
        <div class="max-w-2xl">
            <p class="mb-3 text-sm font-semibold tracking-wide text-forest uppercase">{{ __('articles.title') }}</p>
            <h1 class="text-4xl leading-tight sm:text-5xl">{{ __('articles.heading') }}</h1>
            <p class="mt-4 text-lg text-ink-soft">{{ __('articles.intro') }}</p>
        </div>

        <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('articles.filter_label') }}">
            @php $chip = 'rounded-full border px-4 py-2 text-sm font-semibold no-underline transition-colors'; @endphp
            <a href="{{ route('articles.index') }}"
               @class([$chip, 'border-forest bg-forest text-white' => $activeTopic === null, 'border-line-strong bg-white text-ink-soft hover:bg-sand' => $activeTopic !== null])
               @if ($activeTopic === null) aria-current="page" @endif>{{ __('articles.all_topics') }}</a>
            @foreach ($topics as $topic)
                <a href="{{ route('articles.index', ['topic' => $topic]) }}"
                   @class([$chip, 'border-forest bg-forest text-white' => $activeTopic === $topic, 'border-line-strong bg-white text-ink-soft hover:bg-sand' => $activeTopic !== $topic])
                   @if ($activeTopic === $topic) aria-current="page" @endif>{{ __('articles.topics.'.$topic) }}</a>
            @endforeach
        </nav>
    </div>
</section>

<section class="container-page py-12">
    @if (! config('articles.reviewed'))
        <p class="alert-warning mb-8">
            <i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i>
            <span>{{ __('articles.draft_notice') }}</span>
        </p>
    @endif

    @if ($articles->isEmpty())
        <p class="text-ink-soft">{{ __('articles.empty') }}</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $article)
                <x-article-card :article="$article" :heading-level="2" />
            @endforeach
        </div>
    @endif
</section>
@endsection
