<?php

namespace Tests\Unit\Safety;

use App\Services\Safety\RuleBasedSafetyClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the rule layer honest against the shared fictional test set used by
 * `php artisan aisa:safety-eval`: every "direct" case must be caught by the
 * rules alone, and no "safe" case may be flagged. "Indirect" cases are
 * reported, not required, because they are the AI layer's job.
 */
class SafetyTestSetTest extends TestCase
{
    private function cases(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../resources/safety/test-set.json'), true)['cases'];
    }

    public function test_rules_catch_every_direct_case(): void
    {
        $classifier = new RuleBasedSafetyClassifier;
        $missed = array_filter($this->cases(), fn ($c) => $c['kind'] === 'direct' && ! $classifier->assess($c['text'])->flagged);

        $this->assertSame([], array_column($missed, 'id'));
    }

    public function test_rules_never_flag_safe_cases(): void
    {
        $classifier = new RuleBasedSafetyClassifier;
        $flagged = array_filter($this->cases(), fn ($c) => $c['kind'] === 'safe' && $classifier->assess($c['text'])->flagged);

        $this->assertSame([], array_column($flagged, 'id'));
    }

    public function test_the_set_covers_every_language_and_kind(): void
    {
        $cases = $this->cases();

        foreach (['en', 'sw', 'mixed'] as $lang) {
            foreach (['direct', 'indirect', 'safe'] as $kind) {
                $this->assertNotEmpty(array_filter($cases, fn ($c) => $c['lang'] === $lang && $c['kind'] === $kind), "No {$kind} case in {$lang}");
            }
        }
    }
}
