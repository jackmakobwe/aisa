<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Ticket;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ConversationAi;
use App\Services\LanguageDetector;
use App\Services\TicketCredentialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Runs the quality test set (resources/quality/test-set.json) through the
 * full AI pipeline (reference notes, prompt, configured provider) and
 * checks each reply automatically. Nothing is saved to the database: each
 * case runs inside a transaction that is rolled back.
 *
 * Use it to compare models and to see the effect of prompt or knowledge
 * changes. The checks catch clear failures only; a person should still
 * read the replies in the saved report.
 */
class QualityEvaluate extends Command
{
    protected $signature = 'aisa:quality-eval
        {--only= : Comma-separated case ids to run}
        {--set= : Path to another test set JSON file}';

    protected $description = 'Check AI reply quality against the quality test set';

    /** Invented phone numbers: Tanzanian mobiles and generic 3-3-3 patterns. */
    private const PHONE = '/(?:\+?255|\b0)[67]\d{8}\b|\b\d{3}[\s-]\d{3}[\s-]\d{3,4}\b/';

    public function handle(ConversationAi $ai, TicketCredentialService $credentials, LanguageDetector $languages): int
    {
        $path = $this->option('set') ?: resource_path('quality/test-set.json');
        $cases = json_decode((string) @file_get_contents($path), true)['cases'] ?? null;
        if (! is_array($cases) || $cases === []) {
            $this->error("No cases found in {$path}");

            return self::FAILURE;
        }
        if ($only = $this->option('only')) {
            $ids = array_map('trim', explode(',', $only));
            $cases = array_values(array_filter($cases, fn ($c) => in_array($c['id'], $ids, true)));
        }

        $provider = $ai->provider();
        if ($provider->isMock()) {
            $this->warn('The configured provider is the development MOCK: results say nothing about a real model.');
        }
        $this->info("Provider: {$provider->name()} · {$this->modelName($provider->name())} · ".count($cases).' case(s)');

        $results = [];
        foreach ($cases as $i => $case) {
            $this->line(sprintf('[%d/%d] %s ...', $i + 1, count($cases), $case['id']));
            $run = $this->runCase($ai, $credentials, $case);
            $failed = $run['error'] !== null ? ['error: '.$run['error']] : $this->check($case, $run, $languages);

            $results[] = [
                'id' => $case['id'],
                'passed' => $failed === [],
                'failed_checks' => $failed,
                'seconds' => $run['seconds'],
                'notes' => $run['notes'],
                'text' => $case['text'],
                'reply' => $run['reply'],
            ];
        }

        $this->newLine();
        $this->table(['Case', 'Result', 'Seconds', 'Problems'], array_map(fn ($r) => [
            $r['id'], $r['passed'] ? 'ok' : 'FAIL', $r['seconds'], Str::limit(implode('; ', $r['failed_checks']), 70),
        ], $results));

        $passed = count(array_filter($results, fn ($r) => $r['passed']));
        $file = 'quality-eval/'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($file, json_encode([
            'run_at' => now()->toIso8601String(),
            'provider' => $provider->name(),
            'model' => $this->modelName($provider->name()),
            'prompt_version' => config('aisa.ai.prompt_version'),
            'summary' => ['cases' => count($results), 'passed' => $passed],
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line(sprintf('Passed %d of %d (%d%%). Replies for review: %s', $passed, count($results), count($results) ? round(100 * $passed / count($results)) : 0, Storage::disk('local')->path($file)));

        return $passed === count($results) ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{reply: ?string, flag: bool, notes: list<string>, seconds: float, error: ?string} */
    private function runCase(ConversationAi $ai, TicketCredentialService $credentials, array $case): array
    {
        $started = microtime(true);
        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'reference' => $credentials->newReference(),
                'locale' => $case['lang'] === 'sw' ? 'sw' : 'en',
                'module' => $case['module'] ?? null,
                'status' => Ticket::STATUS_OPEN,
                'last_activity_at' => now(),
            ]);
            $ticket->messages()->create(['sender' => Message::SENDER_USER, 'body' => $case['text']]);
            $outcome = $ai->turn($ticket);

            return ['reply' => $outcome->reply, 'flag' => $outcome->risk->flagged, 'notes' => $outcome->knowledgeIds, 'seconds' => round(microtime(true) - $started, 1), 'error' => null];
        } catch (AiUnavailableException $e) {
            return ['reply' => null, 'flag' => false, 'notes' => [], 'seconds' => round(microtime(true) - $started, 1), 'error' => $e->reason];
        } finally {
            DB::rollBack();
        }
    }

    /** @return list<string> names of the checks that failed */
    private function check(array $case, array $run, LanguageDetector $languages): array
    {
        $reply = (string) $run['reply'];
        $lower = mb_strtolower($reply);
        $failed = [];

        // A first reply always says it is an AI.
        if (! preg_match('/\bAI\b/u', $reply)) {
            $failed[] = 'does not say it is an AI';
        }

        $language = $languages->detect($reply);
        if ($language !== 'mixed' && $language !== $case['lang']) {
            $failed[] = "replied in {$language}, expected {$case['lang']}";
        }

        if (! empty($case['include_any']) && ! collect($case['include_any'])->contains(fn ($w) => str_contains($lower, mb_strtolower($w)))) {
            $failed[] = 'missing any of: '.implode(', ', $case['include_any']);
        }
        foreach ($case['exclude'] ?? [] as $word) {
            if (str_contains($lower, mb_strtolower($word))) {
                $failed[] = "contains \"{$word}\"";
            }
        }

        if (isset($case['max_words']) && str_word_count(Str::ascii($reply)) > $case['max_words']) {
            $failed[] = "longer than {$case['max_words']} words";
        }
        if (preg_match(self::PHONE, $reply)) {
            $failed[] = 'contains a phone number';
        }
        if (preg_match('/\*\*|^#{1,6}\s|^\|.*\|$/m', $reply)) {
            $failed[] = 'uses markdown';
        }
        if (! empty($case['expect_flag']) && ! $run['flag']) {
            $failed[] = 'risk not flagged by the model';
        }
        if (isset($case['budget_total']) && ($sum = $this->budgetSum($reply, $case['budget_total'])) !== $case['budget_total']) {
            $failed[] = 'budget lines add up to '.number_format($sum).', not '.number_format($case['budget_total']);
        }

        return $failed;
    }

    /** Sum of the amounts on list lines, ignoring the line that states the total. */
    private function budgetSum(string $reply, int $total): int
    {
        $sum = 0;
        foreach (preg_split('/\R/', $reply) as $line) {
            if (! preg_match('/^\s*(?:[-•*]|\d+[.)])\s/u', $line)) {
                continue;
            }
            if (! preg_match_all('/\d{1,3}(?:[,. ]\d{3})+|\d{4,}/', $line, $m)) {
                continue;
            }
            $amount = (int) preg_replace('/\D/', '', end($m[0]));
            if ($amount !== $total) {
                $sum += $amount;
            }
        }

        return $sum;
    }

    private function modelName(string $provider): string
    {
        return match ($provider) {
            'ollama' => config('aisa.ai.ollama.model'),
            'anthropic' => config('aisa.ai.model'),
            default => $provider,
        };
    }
}
