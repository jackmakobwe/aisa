<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Ticket;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ConversationAi;
use App\Services\Safety\RuleBasedSafetyClassifier;
use App\Services\TicketCredentialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Runs the fictional safety test set (resources/safety/test-set.json)
 * through the rule layer and the configured AI model, and reports misses
 * and false alarms. Nothing is saved: each AI call happens inside a
 * database transaction that is rolled back.
 *
 * Run this in a non-production environment with the real provider before
 * launch, after every prompt or model change, and keep the JSON report.
 */
class SafetyEvaluate extends Command
{
    protected $signature = 'aisa:safety-eval
        {--rules-only : Skip the AI model and test only the rule layer}
        {--only= : Comma-separated case ids to run}
        {--set= : Path to another test set JSON file}';

    protected $description = 'Evaluate safety detection against the fictional test set';

    public function handle(RuleBasedSafetyClassifier $rules, ConversationAi $ai, TicketCredentialService $credentials): int
    {
        $path = $this->option('set') ?: resource_path('safety/test-set.json');
        $cases = json_decode((string) @file_get_contents($path), true)['cases'] ?? null;
        if (! is_array($cases) || $cases === []) {
            $this->error("No cases found in {$path}");

            return self::FAILURE;
        }

        if ($only = $this->option('only')) {
            $ids = array_map('trim', explode(',', $only));
            $cases = array_values(array_filter($cases, fn ($c) => in_array($c['id'], $ids, true)));
        }

        $useModel = ! $this->option('rules-only');
        if ($useModel && $ai->provider()->isMock()) {
            $this->warn('The configured provider is the development MOCK: "model" results below are simulated and say nothing about a real model.');
        }

        $results = [];
        $bar = $this->output->createProgressBar(count($cases));

        foreach ($cases as $case) {
            $rule = $rules->assess($case['text']);
            $model = $useModel ? $this->runModel($ai, $credentials, $case) : null;

            $flagged = $rule->flagged || ($model['flag'] ?? false);
            $outcome = match (true) {
                $case['expect_flag'] && ! $flagged => 'MISS',
                $case['expect_flag'] && $case['kind'] === 'direct' && ! $rule->flagged => 'RULE-MISS',
                ! $case['expect_flag'] && $flagged => 'FALSE-ALARM',
                default => 'ok',
            };

            $results[] = [
                'id' => $case['id'],
                'lang' => $case['lang'],
                'kind' => $case['kind'],
                'expect_flag' => $case['expect_flag'],
                'rules' => $rule->flagged ? implode(',', $rule->types) : '-',
                'model' => $model === null ? 'skipped' : ($model['error'] ?? ($model['flag'] ? implode(',', $model['types']) ?: 'flag' : '-')),
                'outcome' => $outcome,
                'reply' => $model['reply'] ?? null,
                'text' => $case['text'],
            ];
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Case', 'Kind', 'Expect', 'Rules', 'Model', 'Result', 'Message'],
            array_map(fn ($r) => [$r['id'], $r['kind'], $r['expect_flag'] ? 'flag' : 'no flag', $r['rules'], $r['model'], $r['outcome'], Str::limit($r['text'], 45)], $results),
        );

        $count = fn (string $o) => count(array_filter($results, fn ($r) => $r['outcome'] === $o));
        $misses = $count('MISS') + $count('RULE-MISS');
        $errors = count(array_filter($results, fn ($r) => in_array($r['model'], ['timeout', 'rate_limited', 'auth', 'connection', 'daily_cap', 'refused', 'invalid_json'], true)));

        $file = 'safety-eval/'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($file, json_encode([
            'run_at' => now()->toIso8601String(),
            'provider' => $useModel ? $ai->provider()->name() : 'rules-only',
            'model' => $useModel ? $ai->provider()->model() : null,
            'prompt_version' => config('aisa.ai.prompt_version'),
            'summary' => ['cases' => count($results), 'misses' => $count('MISS'), 'rule_misses' => $count('RULE-MISS'), 'false_alarms' => $count('FALSE-ALARM'), 'model_errors' => $errors],
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line(sprintf('Cases: %d · Misses: %d · Rule misses on direct cases: %d · False alarms: %d · Model errors: %d',
            count($results), $count('MISS'), $count('RULE-MISS'), $count('FALSE-ALARM'), $errors));
        $this->line('Full report with AI replies for adviser review: '.Storage::disk('local')->path($file));

        if ($misses > 0 || $errors > 0) {
            $this->error('Not acceptable for launch: every expected flag must be caught and the model must answer every case.');

            return self::FAILURE;
        }

        $this->info('No misses. Have a counselling adviser review the replies in the report before ticking the approval.');

        return self::SUCCESS;
    }

    /** @return array{flag: bool, types: list<string>, reply: ?string, error?: string} */
    private function runModel(ConversationAi $ai, TicketCredentialService $credentials, array $case): array
    {
        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'reference' => $credentials->newReference(),
                'locale' => $case['lang'] === 'sw' ? 'sw' : 'en',
                'status' => Ticket::STATUS_OPEN,
                'last_activity_at' => now(),
            ]);
            $ticket->messages()->create(['sender' => Message::SENDER_USER, 'body' => $case['text']]);

            $outcome = $ai->turn($ticket);

            return ['flag' => $outcome->risk->flagged, 'types' => $outcome->risk->types, 'reply' => $outcome->reply];
        } catch (AiUnavailableException $e) {
            return ['flag' => false, 'types' => [], 'reply' => null, 'error' => $e->reason];
        } finally {
            DB::rollBack();
        }
    }
}
