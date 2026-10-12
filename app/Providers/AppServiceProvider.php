<?php

namespace App\Providers;

use App\Auth\MemberUserProvider;
use App\Models\ContactSubmission;
use App\Models\CrisisResource;
use App\Models\DeletionRequest;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\MockProvider;
use App\Services\LaunchReadiness;
use App\Session\PrivacyDatabaseSessionHandler;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, function ($app) {
            $config = config('aisa.ai');

            if ($config['provider'] === 'anthropic') {
                if (blank($config['api_key'])) {
                    throw new RuntimeException('AISA_AI_PROVIDER=anthropic but ANTHROPIC_API_KEY is empty. Set the key or use AISA_AI_PROVIDER=mock for development.');
                }

                return new AnthropicProvider(
                    apiKey: $config['api_key'],
                    model: $config['model'],
                    effort: $config['effort'],
                    timeout: $config['timeout'],
                    maxRetries: $config['max_retries'],
                    refusalFallback: $config['refusal_fallback'],
                );
            }

            if ($config['provider'] === 'mock') {
                if ($app->isProduction()) {
                    throw new RuntimeException('The mock AI provider cannot be used in production.');
                }

                return $app->make(MockProvider::class);
            }

            throw new RuntimeException("Unknown AISA_AI_PROVIDER [{$config['provider']}].");
        });
    }

    public function boot(): void
    {
        // Database sessions without IP address or user agent for the public.
        Session::extend('aisa-database', function ($app) {
            $config = $app['config']['session'];

            return new PrivacyDatabaseSessionHandler(
                $app['db']->connection($config['connection']),
                $config['table'],
                $config['lifetime'],
                $app,
            );
        });

        Auth::provider('members', fn ($app, array $config) => new MemberUserProvider($app['hash'], $config['model']));

        Vite::useCspNonce();

        // uncompromised() checks HaveIBeenPwned (k-anonymity); skipped in tests to avoid network calls.
        Password::defaults(function () {
            $rule = Password::min(12)->mixedCase()->numbers();

            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        Gate::define('admin', fn (User $user) => $user->is_active && $user->isAdmin());
        Gate::define('respond', fn (User $user) => $user->is_active && in_array($user->role, [User::ROLE_ADMIN, User::ROLE_RESPONDER], true));

        $this->configureRateLimiting();

        View::composer('layouts.admin', function ($view) {
            $view->with('adminCounts', [
                'flags' => SafetyFlag::where('status', '!=', SafetyFlag::STATUS_RESOLVED)->count(),
                'awaiting' => Ticket::where('status', Ticket::STATUS_AWAITING_HUMAN)->count(),
                'deletions' => DeletionRequest::where('status', DeletionRequest::STATUS_PENDING)->count(),
                'contacts' => ContactSubmission::where('status', 'new')->count(),
            ]);
        });

        // Outside production, every public page says plainly what is not real yet.
        View::composer('layouts.public', function ($view) {
            if ($this->app->isProduction()) {
                $view->with('devNotices', []);

                return;
            }

            $notices = [];
            if (config('aisa.ai.provider') === 'mock') {
                $notices[] = __('ui.dev_mock');
            }
            if (! CrisisResource::verified()->exists()) {
                $notices[] = __('ui.dev_contacts');
            }
            if (! app(LaunchReadiness::class)->isReady()) {
                $notices[] = __('ui.dev_not_ready');
            }
            $view->with('devNotices', $notices);
        });
    }

    private function configureRateLimiting(): void
    {
        // Rate-limit keys use a hash of the IP so raw addresses are not kept in the cache.
        $ip = fn (Request $r) => hash('sha256', (string) $r->ip());

        RateLimiter::for('messages', fn (Request $r) => [
            // Per conversation when one is open (stable across session id changes), else per session.
            Limit::perMinute(config('aisa.limits.messages_per_minute'))->by('msg:'.($r->session()->get('aisa.ticket_id') ? 'ticket-'.$r->session()->get('aisa.ticket_id') : 'session-'.$r->session()->getId())),
            Limit::perMinute(30)->by('msg-ip:'.$ip($r)),
        ]);

        RateLimiter::for('start', fn (Request $r) => Limit::perHour(10)->by('start:'.$ip($r)));

        RateLimiter::for('code-lookup', fn (Request $r) => Limit::perHour(30)->by('code-ip:'.$ip($r)));

        RateLimiter::for('contact', fn (Request $r) => Limit::perHour(config('aisa.limits.contact_per_hour'))->by('contact:'.$ip($r)));

        // Member sign-in, sign-up and email links. Failed passwords are also limited per email in MemberAuthController.
        RateLimiter::for('member-auth', fn (Request $r) => Limit::perMinute(10)->by('member-auth:'.$ip($r)));

        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(120)->by('admin:'.($r->user()?->id ?? $ip($r))));
    }
}
