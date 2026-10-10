@extends('layouts.admin')

@section('title', $response->exists ? 'Edit saved response' : 'New saved response')

@section('content')
<a href="{{ route('admin.saved-responses.index') }}" class="inline-flex items-center gap-1 text-sm"><i data-lucide="arrow-left" class="h-4 w-4"></i>Saved responses</a>
<h1 class="mt-3 text-3xl">{{ $response->exists ? 'Edit saved response' : 'New saved response' }}</h1>

<form method="POST" action="{{ $response->exists ? route('admin.saved-responses.update', $response) : route('admin.saved-responses.store') }}" class="card mt-6 max-w-3xl space-y-5">
    @csrf
    @if ($response->exists) @method('PUT') @endif
    <div>
        <label for="title" class="field-label">Title</label>
        <input id="title" name="title" type="text" required maxlength="120" class="field" value="{{ old('title', $response->title) }}">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="language" class="field-label">Language</label>
            <select id="language" name="language" class="field">
                <option value="en" @selected(old('language', $response->language) === 'en')>English</option>
                <option value="sw" @selected(old('language', $response->language) === 'sw')>Kiswahili</option>
            </select>
        </div>
        <div>
            <label for="category" class="field-label">Category <span class="font-normal text-ink-muted">(optional)</span></label>
            <select id="category" name="category" class="field">
                <option value="">Any</option>
                @foreach (config('aisa.categories') as $c)
                    <option value="{{ $c }}" @selected(old('category', $response->category) === $c)>{{ \App\Support\Format::label($c) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label for="body" class="field-label">Text</label>
        <textarea id="body" name="body" rows="10" required maxlength="6000" class="field">{{ old('body', $response->body) }}</textarea>
    </div>
    <button type="submit" class="btn-primary">Save</button>
</form>
@endsection
