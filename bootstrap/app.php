<?php

use App\Http\Middleware\AdminSession;
use App\Http\Middleware\EnsureLaunchReady;
use App\Http\Middleware\RequireMember;
use App\Http\Middleware\RequireTicketAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'launch-ready' => EnsureLaunchReady::class,
            'ticket' => RequireTicketAccess::class,
            'admin.session' => AdminSession::class,
            'member' => RequireMember::class,
        ]);

        // The language cookie is not secret; everything else stays encrypted.
        $middleware->encryptCookies(except: [SetLocale::COOKIE]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Secrets are never flashed back into the session after a validation error.
        $exceptions->dontFlash(['code', 'password', 'password_confirmation', 'current_password', 'one_time_password', 'recovery_code']);
    })->create();
