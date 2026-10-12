<?php

use App\Models\Member;
use App\Models\User;
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
