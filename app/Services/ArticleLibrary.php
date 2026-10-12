<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Educational articles. Order, topic and icon come from config/articles.php;
 * the text comes from lang/{locale}/articles.php, so every article is
 * available in both languages and reviewed like the rest of the site's text.
 */
class ArticleLibrary
{
    private const WORDS_PER_MINUTE = 200;

    /** @return Collection<int, array> All articles in display order. */
    public function all(): Collection
    {
        return collect(config('articles.items'))
            ->map(fn (array $meta, string $slug) => $this->build($slug, $meta))
            ->values();
    }

    public function find(string $slug): ?array
    {
        $meta = config("articles.items.{$slug}");

        return is_array($meta) ? $this->build($slug, $meta) : null;
    }

    public function forTopic(?string $topic): Collection
    {
        return $topic === null ? $this->all() : $this->all()->where('topic', $topic)->values();
    }

    public function latest(int $count): Collection
    {
        return $this->all()->take($count);
    }

    /** Other articles, same topic first, for the "read next" list. */
    public function related(array $article, int $count = 2): Collection
    {
        return $this->all()
            ->reject(fn (array $other) => $other['slug'] === $article['slug'])
            ->sortBy(fn (array $other) => $other['topic'] === $article['topic'] ? 0 : 1)
            ->take($count)
            ->values();
    }

    /** @return list<string> */
    public function topics(): array
    {
        return config('articles.topics');
    }

    private function build(string $slug, array $meta): array
    {
        $text = __("articles.items.{$slug}");
        $sections = $text['sections'] ?? [];

        return [
            'slug' => $slug,
            'topic' => $meta['topic'],
            'icon' => $meta['icon'],
            'title' => $text['title'] ?? $slug,
            'excerpt' => $text['excerpt'] ?? '',
            'sections' => $sections,
            'minutes' => $this->readingMinutes($text['excerpt'] ?? '', $sections),
            'photo' => $this->photo($slug, $meta, $text),
        ];
    }

    /** The article's photo, or null (the icon cover is used) when its files are missing. */
    private function photo(string $slug, array $meta, array $text): ?array
    {
        if (empty($meta['photo'])) {
            return null;
        }

        foreach ([640, 1200] as $width) {
            if (! is_file(public_path("images/articles/{$slug}-{$width}.webp"))) {
                return null;
            }
        }

        return [
            'small' => asset("images/articles/{$slug}-640.webp"),
            'large' => asset("images/articles/{$slug}-1200.webp"),
            'alt' => $text['image_alt'] ?? '',
            'credit' => $meta['photo']['credit'],
            'source' => $meta['photo']['source'],
        ];
    }

    private function readingMinutes(string $excerpt, array $sections): int
    {
        $words = str_word_count($excerpt);

        foreach ($sections as $section) {
            foreach (array_merge([$section['title'] ?? ''], $section['paragraphs'] ?? [], $section['points'] ?? []) as $line) {
                $words += str_word_count($line);
            }
        }

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }
}
