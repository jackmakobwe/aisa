<?php

use App\Models\Member;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\KnowledgeBase;
use App\Services\Ai\Prompts;
use App\Services\AuditLogger;
use App\Services\DeletionService;
use App\Services\LaunchReadiness;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
| Create a dashboard account from the server. This is the only way to make
| the first admin: there is no public registration and no default account.
*/
Artisan::command('aisa:create-admin {--role=admin}', function (AuditLogger $audit) {
    $name = $this->ask('Name');
    $email = $this->ask('Email');
    $password = $this->secret('Password (min 12 chars, mixed case, a number)');
    $role = $this->option('role');

    $validator = Validator::make(compact('name', 'email', 'password', 'role'), [
        'name' => ['required', 'string', 'max:120'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', Password::defaults()],
        'role' => ['required', 'in:admin,responder'],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $user = User::create(compact('name', 'email', 'password', 'role'));
    $audit->log('user.created_cli', $user, ['role' => $role], $user->id);
    $this->info("Created {$role} account for {$email}. Sign in at /admin/login to set up two-factor authentication.");

    return 0;
})->purpose('Create an Aisa dashboard account');

Artisan::command('aisa:launch-check', function (LaunchReadiness $readiness) {
    $checks = $readiness->checks();
    $this->table(['', 'Check'], array_map(fn ($c) => [$c['ok'] ? 'OK' : 'BLOCKER', $c['label']], $checks));
    $blockers = count(array_filter($checks, fn ($c) => ! $c['ok']));
    $blockers ? $this->error("{$blockers} launch blocker(s). The public conversation intake stays closed in production.") : $this->info('All launch checks pass.');

    return $blockers ? 1 : 0;
})->purpose('List launch blockers (verified contacts, legal review, etc.)');

/*
| Send one test message to the configured AI provider and show the reply,
| without creating a conversation: php artisan aisa:ai-check "Mambo".
*/
Artisan::command('aisa:ai-check {message?} {--module=}', function (AiProvider $provider, KnowledgeBase $knowledge) {
    $message = $this->argument('message') ?: 'Habari, nina mshahara wa TSh 300,000 kwa mwezi. Nigawanyeje matumizi yangu?';
    $module = $this->option('module') ?: null;
    $notes = $knowledge->search($message, $module);

    $this->info("Provider: {$provider->name()}".($provider->name() === 'ollama' ? ' ('.config('aisa.ai.ollama.model').')' : ''));
    $this->line('Reference notes: '.(implode(', ', array_map(fn ($n) => $n->id, $notes)) ?: 'none'));
    $this->line('Waiting for the reply (a local model can take a few minutes)...');

    $system = Prompts::turnSystem(config('aisa.assistant_name'), 0, config('aisa.ai.max_follow_up_questions'), false, 'sw', config('aisa.response_window_hours'), $module, $notes);

    try {
        $result = $provider->generateJson($system, [['role' => 'user', 'content' => $message]], Prompts::turnSchema(), 2000);
    } catch (AiUnavailableException $e) {
        $this->error("The AI could not reply: {$e->reason}");
        $this->line(match ($e->reason) {
            'connection' => 'Is Ollama running? Start the Ollama app, or run: ollama serve',
            'model_missing' => 'Download the model first: ollama pull '.config('aisa.ai.ollama.model'),
            'timeout' => 'The model took too long. Try a smaller model or raise AISA_OLLAMA_TIMEOUT.',
            default => 'See storage/logs/laravel.log for details.',
        });

        return 1;
    }

    $this->newLine();
    $this->line((string) ($result->data['reply'] ?? '(no reply field)'));
    $this->newLine();
    $this->table(['Category', 'Urgency', 'Risk', 'Seconds', 'Tokens in/out'], [[
        $result->data['category'] ?? '-',
        $result->data['urgency'] ?? '-',
        json_encode($result->data['risk'] ?? null),
        round($result->latencyMs / 1000, 1),
        ($result->inputTokens ?? '?').' / '.($result->outputTokens ?? '?'),
    ]]);

    return 0;
})->purpose('Send a test message to the configured AI provider');

/*
| Review the AI reference notes, or see which notes a test message would
| give the model: php artisan aisa:knowledge "Nina mshahara wa 300,000".
*/
Artisan::command('aisa:knowledge {message?} {--module=}', function (KnowledgeBase $knowledge) {
    if ($message = $this->argument('message')) {
        $notes = $knowledge->search($message, $this->option('module') ?: null);
        $notes === []
            ? $this->warn('No reference notes match. The AI answers from its general instructions only.')
            : $this->table(['Id', 'Title'], array_map(fn ($n) => [$n->id, $n->title], $notes));

        return 0;
    }

    $notes = $knowledge->all();
    $this->table(['Module', 'Id', 'Title', 'Keywords', 'Reviewed'], $notes->map(fn ($n) => [
        $n->module, $n->id, $n->title, count($n->keywords), $n->reviewed ? 'yes' : 'NO',
    ])->all());
    $pending = $notes->reject(fn ($n) => $n->reviewed)->count();
    $pending ? $this->warn("{$pending} of {$notes->count()} note(s) still need professional review.") : $this->info('All notes are reviewed.');

    return 0;
})->purpose('List the AI reference notes, or test which notes match a message');

Artisan::command('aisa:prune', function (DeletionService $deletions) {
    $count = $deletions->pruneExpired();
    $this->info("Deleted {$count} conversation(s) inactive for more than ".config('aisa.retention_days').' days.');

    // Sign-ups whose email was never confirmed hold no conversations (only confirmed
    // accounts can) and are removed after a week.
    $unconfirmed = Member::whereNull('email_verified_at')->where('created_at', '<', now()->subDays(7))->delete();
    $this->info("Deleted {$unconfirmed} unconfirmed account(s) older than 7 days.");
})->purpose('Delete conversations past the retention period and unconfirmed accounts');

Artisan::command('aisa:pepper', function () {
    $this->line('AISA_TICKET_PEPPER='.bin2hex(random_bytes(32)));
    $this->warn('Set this once before launch. Changing it later makes every existing ticket code invalid and stops every account from signing in.');
})->purpose('Generate a secret for hashing ticket codes');

Schedule::command('aisa:prune')->dailyAt('02:30');
