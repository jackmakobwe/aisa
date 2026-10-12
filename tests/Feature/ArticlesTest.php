<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_article_has_complete_text_in_both_languages(): void
    {
        $topics = config('articles.topics');

        foreach (config('articles.items') as $slug => $meta) {
            $this->assertContains($meta['topic'], $topics, "{$slug} has an unknown topic");
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);

            $en = trans("articles.items.{$slug}", [], 'en');
            $sw = trans("articles.items.{$slug}", [], 'sw');

            foreach (['en' => $en, 'sw' => $sw] as $locale => $text) {
                $this->assertIsArray($text, "{$slug} is missing in {$locale}");
                $this->assertNotEmpty($text['title'] ?? null, "{$slug} has no {$locale} title");
                $this->assertNotEmpty($text['excerpt'] ?? null, "{$slug} has no {$locale} excerpt");
                $this->assertNotEmpty($text['sections'] ?? null, "{$slug} has no {$locale} sections");
                if (! empty($meta['photo'])) {
                    $this->assertNotEmpty($text['image_alt'] ?? null, "{$slug} has no {$locale} image description");
                }
            }

            if (! empty($meta['photo'])) {
                $this->assertNotEmpty($meta['photo']['credit']);
                $this->assertStringStartsWith('https://unsplash.com/photos/', $meta['photo']['source']);
                foreach ([640, 1200] as $width) {
                    $this->assertFileExists(public_path("images/articles/{$slug}-{$width}.webp"));
                }
            }

            // Same shape in both languages, so a translation never silently drops a point.
            $this->assertSame($this->shape($en['sections']), $this->shape($sw['sections']), "{$slug} differs between en and sw");
        }

        foreach ($topics as $topic) {
            $this->assertNotSame("articles.topics.{$topic}", trans("articles.topics.{$topic}", [], 'sw'));
        }
    }

    public function test_home_shows_the_latest_articles_and_the_talk_to_aisa_panel(): void
    {
        $first = array_key_first(config('articles.items'));

        $this->get('/')->assertOk()
            ->assertSee('Talk to Aisa')
            ->assertSee('A real person, not an AI')
            ->assertSee(route('articles.show', $first), false)
            ->assertSee(trans("articles.items.{$first}.title", [], 'en'))
            ->assertSee(route('articles.index'), false);

        $this->withSession(['locale' => 'sw'])->get('/')
            ->assertSee('Ongea na Aisa')
            ->assertSee(trans("articles.items.{$first}.title", [], 'sw'));
    }

    public function test_article_index_lists_all_articles_and_filters_by_topic(): void
    {
        $response = $this->get(route('articles.index'))->assertOk();
        foreach (array_keys(config('articles.items')) as $slug) {
            $response->assertSee(route('articles.show', $slug), false);
        }

        $girls = collect(config('articles.items'))->filter(fn ($meta) => $meta['topic'] === 'girls');
        $others = collect(config('articles.items'))->reject(fn ($meta) => $meta['topic'] === 'girls');

        $filtered = $this->get(route('articles.index', ['topic' => 'girls']))->assertOk();
        foreach ($girls->keys() as $slug) {
            $filtered->assertSee(route('articles.show', $slug), false);
        }
        foreach ($others->keys() as $slug) {
            $filtered->assertDontSee(route('articles.show', $slug), false);
        }

        // An unknown topic falls back to every article.
        $this->get(route('articles.index', ['topic' => 'nonsense']))->assertOk()
            ->assertSee(route('articles.show', $others->keys()->first()), false);
    }

    public function test_article_page_renders_in_both_languages_with_safety_links(): void
    {
        foreach (array_keys(config('articles.items')) as $slug) {
            foreach (['en' => 'Quick exit', 'sw' => 'Toka haraka'] as $locale => $quickExit) {
                $this->withSession(['locale' => $locale])->get(route('articles.show', $slug))
                    ->assertOk()
                    ->assertSee(trans("articles.items.{$slug}.title", [], $locale))
                    ->assertSee($quickExit)
                    ->assertSee(route('get-help'), false)
                    ->assertSee(route('home').'#start', false);
            }
        }
    }

    public function test_article_photos_are_served_locally_with_alt_text_and_credit(): void
    {
        $slug = 'periods-mood-and-wellbeing';
        $meta = config("articles.items.{$slug}");

        $this->get(route('articles.show', $slug))->assertOk()
            ->assertSee(asset("images/articles/{$slug}-1200.webp"), false)
            ->assertSee('alt="'.e(trans("articles.items.{$slug}.image_alt", [], 'en')).'"', false)
            ->assertSee($meta['photo']['credit'])
            ->assertDontSee('images.unsplash.com', false);

        $this->get('/')->assertSee(asset('images/articles/'.array_key_first(config('articles.items')).'-640.webp'), false);
    }

    public function test_articles_say_they_are_drafts_until_reviewed(): void
    {
        $slug = array_key_first(config('articles.items'));

        config(['articles.reviewed' => false]);
        $this->get(route('articles.show', $slug))->assertSee('waiting for review by a qualified counsellor');

        config(['articles.reviewed' => true]);
        $this->get(route('articles.show', $slug))->assertDontSee('waiting for review by a qualified counsellor');
    }

    public function test_unknown_article_is_not_found(): void
    {
        $this->get('/articles/does-not-exist')->assertNotFound();
    }

    private function shape(array $sections): array
    {
        return array_map(fn (array $section) => [
            'paragraphs' => count($section['paragraphs'] ?? []),
            'points' => count($section['points'] ?? []),
        ], $sections);
    }
}
