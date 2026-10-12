<?php

namespace Tests\Unit;

use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\KnowledgeNote;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/knowledge-'.uniqid());
        File::ensureDirectoryExists($this->dir.'/money');
        File::ensureDirectoryExists($this->dir.'/parenting');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function note(string $path, string $id, string $keywords, bool $reviewed = false, string $body = 'Body text.'): void
    {
        File::put("{$this->dir}/{$path}", "---\nid: {$id}\ntitle: Title of {$id}\nkeywords: {$keywords}\nreviewed: ".($reviewed ? 'true' : 'false')."\n---\n{$body}\n");
    }

    public function test_parses_notes_with_module_from_folder(): void
    {
        $this->note('money/budget.md', 'money-budget', 'Budget, Bajeti, mshahara', true);
        File::put($this->dir.'/money/_draft.md', "---\nid: ignored\nkeywords: budget\n---\nIgnored\n");
        File::put($this->dir.'/money/broken.md', 'No header here');

        $notes = (new KnowledgeBase($this->dir))->all();

        $this->assertCount(1, $notes);
        $note = $notes->first();
        $this->assertSame('money-budget', $note->id);
        $this->assertSame('money', $note->module);
        $this->assertSame(['budget', 'bajeti', 'mshahara'], $note->keywords);
        $this->assertTrue($note->reviewed);
    }

    public function test_matches_english_kiswahili_and_word_forms(): void
    {
        $this->note('money/debt.md', 'money-debt', 'mkopo, kukopa, debt');
        $this->note('parenting/discipline.md', 'parenting-discipline', 'discipline, kumpiga');
        $kb = new KnowledgeBase($this->dir);

        $this->assertSame(['money-debt'], $this->ids($kb->search('Nimechukua MKOPO wa simu')));
        // Longer keywords may sit inside a Kiswahili word (prefixes).
        $this->assertSame(['parenting-discipline'], $this->ids($kb->search('Je, nimkumpiga mtoto?')));
        // Short keywords must be whole words.
        $this->assertSame([], $this->ids($kb->search('indebted')));
        $this->assertSame([], $this->ids($kb->search('Habari za leo')));
        $this->assertSame([], $this->ids($kb->search('   ')));
    }

    public function test_more_matches_rank_first_and_module_breaks_ties(): void
    {
        $this->note('money/a.md', 'money-a', 'save, akiba');
        $this->note('parenting/b.md', 'parenting-b', 'save, child');
        $this->note('money/c.md', 'money-c', 'unrelated');
        $kb = new KnowledgeBase($this->dir);

        $this->assertSame(['parenting-b', 'money-a'], $this->ids($kb->search('How can I save for my child?')));
        $this->assertSame('money-a', $kb->search('I want to save', 'money')[0]->id);
        $this->assertSame('parenting-b', $kb->search('I want to save', 'parenting')[0]->id);
        $this->assertCount(1, $kb->search('save', null, 1));
    }

    public function test_all_reviewed_needs_every_note_reviewed(): void
    {
        $this->assertFalse((new KnowledgeBase($this->dir))->allReviewed(), 'An empty library is not reviewed');

        $this->note('money/a.md', 'money-a', 'save', true);
        $this->assertTrue((new KnowledgeBase($this->dir))->allReviewed());

        $this->note('money/b.md', 'money-b', 'loan', false);
        $this->assertFalse((new KnowledgeBase($this->dir))->allReviewed());
    }

    public function test_shipped_library_is_valid_and_covers_every_module(): void
    {
        $notes = app(KnowledgeBase::class)->all();

        $this->assertGreaterThanOrEqual(60, $notes->count());
        $this->assertSame($notes->count(), $notes->pluck('id')->unique()->count(), 'Note ids must be unique');
        foreach (config('aisa.modules') as $module) {
            $this->assertTrue($notes->contains('module', $module), "No notes for {$module}");
        }
        foreach ($notes as $note) {
            $this->assertNotEmpty($note->keywords, "{$note->id} has no keywords");
            // Help contacts come only from verified records, never from notes.
            $this->assertDoesNotMatchRegularExpression('/\+?\d[\d\s-]{6,}\d/', str_replace(['300,000', '100,000'], '', $note->body), "{$note->id} looks like it contains a phone number");
        }
    }

    public function test_shipped_library_answers_the_example_questions(): void
    {
        $kb = app(KnowledgeBase::class);

        foreach ([
            'Nina mshahara wa TSh 300,000 kwa mwezi, nigawanyeje matumizi yangu?' => 'money-budgeting',
            'How can I start a small business with TSh 100,000?' => 'money-small-business',
            'How do I save money when my income is irregular?' => 'money-irregular-income',
            'How can I tell whether an online investment opportunity is a scam?' => 'money-scams',
            'My son is very stubborn, should I beat him?' => 'parenting-positive-discipline',
            'Nina mimba ya miezi saba na natokwa damu' => 'parenting-pregnancy',
            'Nimefeli mtihani wa kidato cha nne' => 'career-academic-pressure',
            'How do I write a CV with no experience?' => 'career-job-search',
            'I feel so lonely since I moved here' => 'wellbeing-loneliness',
            'Nimepoteza namba yangu ya siri' => 'general-about-aisa',
            'Mume wangu ananipiga kila akilewa' => 'wellbeing-abuse-safety',
            'Mtoto wangu mchanga hanyonyi na ana homa' => 'parenting-newborn',
            'How do I stop betting?' => 'money-betting',
            'Nikopa 200,000 kwa riba ya 10% kwa mwezi, nitalipa kiasi gani?' => 'money-maths',
            'My husband takes my salary' => 'money-couples',
            'Nataka kufuga kuku wa kienyeji' => 'money-agribusiness',
            'How do I apply for a HESLB loan?' => 'career-education-funding',
            'How can I use ChatGPT for my business?' => 'career-ai-productivity',
        ] as $question => $expected) {
            $this->assertContains($expected, $this->ids($kb->search($question)), $question);
        }
    }

    /** @param list<KnowledgeNote> $notes */
    private function ids(array $notes): array
    {
        return array_map(fn (KnowledgeNote $n) => $n->id, $notes);
    }
}
