<?php

use App\Models\CrisisResource;
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
use Illuminate\Validation\Rule;
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
| Import starter help contacts (resources/contacts/tanzania.json). Every
| imported contact is UNVERIFIED and hidden until staff check and verify it.
| Contacts that already exist (same name) are left untouched, unless
| --update is given: then UNVERIFIED ones are refreshed from the file.
| Verified contacts are never changed by an import.
*/
Artisan::command('aisa:import-contacts {--file=} {--update}', function (AuditLogger $audit) {
    $path = $this->option('file') ?: resource_path('contacts/tanzania.json');
    $rows = json_decode((string) @file_get_contents($path), true)['contacts'] ?? null;
    if (! is_array($rows)) {
        $this->error("No contacts found in {$path}");

        return 1;
    }

    $fields = ['category', 'name', 'phone', 'url', 'address', 'district', 'region', 'latitude', 'longitude',
        'description_en', 'description_sw', 'hours', 'source_note', 'sort_order'];
    $created = 0;
    $updated = 0;
    $skipped = [];
    foreach ($rows as $row) {
        $validator = Validator::make($row, [
            'category' => ['required', Rule::in(CrisisResource::CATEGORIES)],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'regex:/^[0-9+()\s\-]{3,40}$/', 'required_without_all:url,address'],
            'url' => ['nullable', 'url:https,http'],
            'region' => ['nullable', Rule::in(config('help.regions'))],
        ]);
        if ($validator->fails()) {
            $skipped[] = ($row['name'] ?? '?').': '.$validator->errors()->first();

            continue;
        }
        if ($existing = CrisisResource::where('name', $row['name'])->first()) {
            if (! $this->option('update')) {
                $skipped[] = "{$row['name']}: already exists";
            } elseif ($existing->is_verified) {
                $skipped[] = "{$row['name']}: verified, left unchanged";
            } else {
                // Clear fields the file no longer sets, so stale details do not linger.
                $existing->fill(array_merge(array_fill_keys($fields, null), ['sort_order' => 0], array_intersect_key($row, array_flip($fields))))->save();
                $audit->log('resource.import_updated', $existing);
                $updated++;
            }

            continue;
        }

        $resource = new CrisisResource(array_intersect_key($row, array_flip($fields)));
        $resource->forceFill(['is_verified' => false, 'verified_by' => null, 'verified_at' => null])->save();
        $audit->log('resource.imported', $resource);
        $created++;
    }

    $this->info("Imported {$created} contact(s), updated {$updated}. All are UNVERIFIED and hidden until staff verify them in Admin > Crisis contacts.");
    foreach ($skipped as $line) {
        $this->line("  skipped: {$line}");
    }

    return 0;
})->purpose('Import starter help contacts (always unverified)');

/*
| Send one test message to the configured AI provider and show the reply,
| without creating a conversation: php artisan aisa:ai-check "Mambo".
*/
Artisan::command('aisa:ai-check {message?} {--module=}', function (AiProvider $provider, KnowledgeBase $knowledge) {
    $message = $this->argument('message') ?: 'Habari, nina mshahara wa TSh 300,000 kwa mwezi. Nigawanyeje matumizi yangu?';
    $module = $this->option('module') ?: null;
    $notes = $knowledge->search($message, $module);

    $this->info("Provider: {$provider->name()} ({$provider->model()})");
    $this->line('Reference notes: '.(implode(', ', array_map(fn ($n) => $n->id, $notes)) ?: 'none'));
    $this->line('Waiting for the reply'.($provider->name() === 'ollama' ? ' (a local model can take a few minutes)' : '').'...');

    $system = Prompts::turnSystem(config('aisa.assistant_name'), 0, config('aisa.ai.max_follow_up_questions'), false, 'sw', config('aisa.response_window_hours'), $module, $notes);

    try {
        $result = $provider->generateJson($system, [['role' => 'user', 'content' => $message]], Prompts::turnSchema(), 2000);
    } catch (AiUnavailableException $e) {
        $this->error("The AI could not reply: {$e->reason}");
        $local = $provider->name() === 'ollama';
        $this->line(match ($e->reason) {
            'connection' => $local ? 'Is Ollama running? Start the Ollama app, or run: ollama serve' : 'Could not reach the API. Check the internet connection.',
            'model_missing' => $local ? 'Download the model first: ollama pull '.$provider->model() : "The API does not know the model \"{$provider->model()}\". Check the model name in .env.",
            'auth' => 'The API key was rejected. Check the key in .env, then run: php artisan config:clear',
            'rate_limited' => 'The free tier limit was reached. Wait a minute, or try again later.',
            'timeout' => $local ? 'The model took too long. Try a smaller model or raise AISA_OLLAMA_TIMEOUT.' : 'The API took too long. Try again.',
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
