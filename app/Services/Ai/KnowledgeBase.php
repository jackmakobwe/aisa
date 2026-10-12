<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Reference notes the AI grounds its answers in (retrieval-augmented
 * generation). Notes are Markdown files in resources/knowledge/<module>/
 * with a small header, so reviewers can edit them without touching code:
 *
 *   ---
 *   id: money-budgeting
 *   title: Making a monthly budget
 *   keywords: budget, bajeti, mshahara, salary
 *   reviewed: false
 *   ---
 *   Note text...
 *
 * Retrieval is keyword based and runs locally: nothing is sent to a third
 * party to build an index, and the result is predictable enough to test.
 */
class KnowledgeBase
{
    /** @var Collection<int, KnowledgeNote>|null */
    private ?Collection $notes = null;

    public function __construct(private readonly string $path) {}

    /** @return Collection<int, KnowledgeNote> */
    public function all(): Collection
    {
        return $this->notes ??= $this->load();
    }

    public function find(string $id): ?KnowledgeNote
    {
        return $this->all()->first(fn (KnowledgeNote $note) => $note->id === $id);
    }

    /**
     * The notes most relevant to what the person wrote. A note needs at
     * least one keyword match; the chosen module only breaks ties.
     *
     * @return list<KnowledgeNote>
     */
    public function search(string $text, ?string $module = null, ?int $limit = null): array
    {
        $text = ' '.$this->normalise($text).' ';
        if (trim($text) === '') {
            return [];
        }

        return $this->all()
            ->map(function (KnowledgeNote $note) use ($text, $module) {
                $hits = 0;
                foreach ($note->keywords as $keyword) {
                    if ($this->matches($text, $keyword)) {
                        $hits++;
                    }
                }

                return ['note' => $note, 'score' => $hits === 0 ? 0 : $hits * 2 + ($note->module === $module ? 1 : 0)];
            })
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit ?? config('aisa.knowledge.max_notes'))
            ->pluck('note')
            ->values()
            ->all();
    }

    /** True when every note has been marked as reviewed. */
    public function allReviewed(): bool
    {
        return $this->all()->isNotEmpty() && $this->all()->every(fn (KnowledgeNote $note) => $note->reviewed);
    }

    /**
     * Short words must match whole words; longer ones may sit inside a word,
     * because Kiswahili adds prefixes and suffixes (kopa → nimekopa).
     */
    private function matches(string $text, string $keyword): bool
    {
        if (mb_strlen($keyword) >= 5) {
            return str_contains($text, $keyword);
        }

        return str_contains($text, ' '.$keyword.' ');
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(Str::ascii($text));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '');
    }

    /** @return Collection<int, KnowledgeNote> */
    private function load(): Collection
    {
        if (! is_dir($this->path)) {
            return collect();
        }

        return collect(File::allFiles($this->path))
            ->filter(fn ($file) => $file->getExtension() === 'md' && ! str_starts_with($file->getFilename(), '_'))
            ->sortBy(fn ($file) => $file->getRelativePathname())
            ->map(fn ($file) => $this->parse($file->getContents(), basename($file->getRelativePath()) ?: 'general'))
            ->filter()
            ->values();
    }

    private function parse(string $contents, string $module): ?KnowledgeNote
    {
        $contents = str_replace("\r\n", "\n", $contents);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $contents, $m)) {
            return null;
        }

        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                $meta[$key] = $value;
            }
        }

        $body = trim($m[2]);
        if (empty($meta['id']) || $body === '') {
            return null;
        }

        $keywords = collect(explode(',', $meta['keywords'] ?? ''))
            ->map(fn ($k) => $this->normalise($k))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new KnowledgeNote(
            id: $meta['id'],
            module: $module,
            title: $meta['title'] ?? $meta['id'],
            keywords: $keywords,
            body: $body,
            reviewed: strtolower($meta['reviewed'] ?? 'false') === 'true',
        );
    }
}
