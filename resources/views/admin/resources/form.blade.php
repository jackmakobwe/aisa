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
        <div class="sm:col-span-2">
            <label for="address" class="field-label">Address <span class="font-normal text-ink-muted">(street, building or landmark)</span></label>
            <input id="address" name="address" type="text" maxlength="255" class="field" placeholder="e.g. Kawawa Road, near Mwananyamala market" value="{{ old('address', $resource->address) }}">
        </div>
        <div>
            <label for="district" class="field-label">District</label>
            <input id="district" name="district" type="text" maxlength="80" class="field" placeholder="e.g. Kinondoni" value="{{ old('district', $resource->district) }}">
        </div>
        <div>
            <label for="region" class="field-label">Region <span class="font-normal text-ink-muted">(empty = national)</span></label>
            <select id="region" name="region" class="field">
                <option value="">National (all regions)</option>
                @foreach (config('help.regions') as $r)
                    <option value="{{ $r }}" @selected(old('region', $resource->region) === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="latitude" class="field-label">Latitude <span class="font-normal text-ink-muted">(optional)</span></label>
            <input id="latitude" name="latitude" type="text" inputmode="decimal" class="field" placeholder="-6.792354" value="{{ old('latitude', $resource->latitude) }}">
        </div>
        <div>
            <label for="longitude" class="field-label">Longitude <span class="font-normal text-ink-muted">(optional)</span></label>
            <input id="longitude" name="longitude" type="text" inputmode="decimal" class="field" placeholder="39.208328" value="{{ old('longitude', $resource->longitude) }}">
        </div>
        <p class="text-xs text-ink-muted sm:col-span-2">To get exact coordinates, right-click the place in Google Maps and click the numbers at the top of the menu to copy them. Without coordinates, "Open in Google Maps" searches for the name and address.</p>
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
    <div>
        <label for="source_note" class="field-label">Source and verification notes <span class="font-normal text-ink-muted">(staff only, never shown publicly)</span></label>
        <textarea id="source_note" name="source_note" rows="2" maxlength="500" class="field" placeholder="Where the details came from, who you called and what they confirmed">{{ old('source_note', $resource->source_note) }}</textarea>
    </div>
    <button type="submit" class="btn-primary">Save</button>
</form>
@endsection
