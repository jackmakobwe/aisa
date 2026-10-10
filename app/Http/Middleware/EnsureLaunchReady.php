<?php

namespace App\Http\Middleware;

use App\Services\LaunchReadiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production, new conversations cannot be started until every launch
 * blocker is resolved (verified crisis contacts, legal review, etc.).
 * Information pages and Get help now stay available regardless.
 */
class EnsureLaunchReady
{
    public function __construct(private readonly LaunchReadiness $readiness) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $this->readiness->isReady()) {
            return redirect()->route('not-open');
        }

        return $next($request);
    }
}
