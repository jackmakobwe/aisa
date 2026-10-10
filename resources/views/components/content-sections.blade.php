@props(['sections'])

{{-- Renders translated content: a list of ['title' => ..., 'paragraphs' => [...], 'points' => [...]]. --}}
<div class="prose-aisa">
    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach ($section['paragraphs'] ?? [] as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
        @if (! empty($section['points']))
            <ul>
                @foreach ($section['points'] as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        @endif
    @endforeach
</div>
