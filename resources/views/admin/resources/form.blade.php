@extends('layouts.admin')

@section('title', $resource->exists ? 'Edit contact' : 'Add contact')

@section('content')
<a href="{{ route('admin.resources.index') }}" class="inline-flex items-center gap-1 text-sm"><i data-lucide="arrow-left" class="h-4 w-4"></i>Crisis contacts</a>
<h1 class="mt-3 text-3xl">{{ $resource->exists ? 'Edit contact' : 'Add contact' }}</h1>
@if ($resource->exists && $resource->is_verified)
    <div class="alert-warning mt-4 max-w-3xl"><i data-lucide="info" class="mt-0.5 h-5 w-5 shrink-0 text-amber"></i><p>Saving changes will unpublish this contact until it is verified again.</p></div>
@endif

<form method="POST" action="{{ $resource->exists ? route('admin.resources.update', $resource) : route('admin.resources.store') }}" class="card mt-6 max-w-3xl space-y-5">
    @csrf
    @if ($resource->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="category" class="field-label">Category</label>
            <select id="category" name="category" class="field" required>
                @foreach (\App\Models\CrisisResource::CATEGORIES as $c)
                    <option value="{{ $c }}" @selected(old('category', $resource->category) === $c)>{{ __("help_page.category_{$c}", [], 'en') }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="name" class="field-label">Organisation or service name</label>
            <input id="name" name="name" type="text" required maxlength="160" class="field" value="{{ old('name', $resource->name) }}">
        </div>
        <div>
            <label for="phone" class="field-label">Phone</label>
            <input id="phone" name="phone" type="text" maxlength="40" class="field" value="{{ old('phone', $resource->phone) }}">
        </div>
        <div>
            <label for="url" class="field-label">Website</label>
            <input id="url" name="url" type="url" maxlength="255" class="field" placeholder="https://" value="{{ old('url', $resource->url) }}">
        </div>
        <div>
            <label for="hours" class="field-label">Hours and languages</label>
            <input id="hours" name="hours" type="text" maxlength="80" class="field" placeholder="e.g. 24 hours · Kiswahili, English" value="{{ old('hours', $resource->hours) }}">
        </div>
        <div>
            <label for="sort_order" class="field-label">Order</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="999" class="field" value="{{ old('sort_order', $resource->sort_order ?? 0) }}">
        </div>
    </div>
    <div>
        <label for="description_en" class="field-label">Description (English)</label>
        <textarea id="description_en" name="description_en" rows="2" maxlength="1000" class="field">{{ old('description_en', $resource->description_en) }}</textarea>
    </div>
    <div>
        <label for="description_sw" class="field-label">Description (Kiswahili)</label>
        <textarea id="description_sw" name="description_sw" rows="2" maxlength="1000" class="field">{{ old('description_sw', $resource->description_sw) }}</textarea>
    </div>
    <button type="submit" class="btn-primary">Save</button>
</form>
@endsection
