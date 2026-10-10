<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to every dashboard route after "auth":
 *  - deactivated accounts are signed out immediately;
 *  - sessions idle longer than the configured limit are ended;
 *  - two-factor authentication must be set up and passed for this session.
 */
class AdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $session = $request->session();

        if (! $user->is_active) {
            return $this->signOut($request, 'This account has been deactivated.');
        }

        $last = $session->get('admin.last_activity');
        if ($last && now()->timestamp - $last > config('aisa.admin.idle_minutes') * 60) {
            return $this->signOut($request, 'You were signed out after a period of inactivity.');
        }
        $session->put('admin.last_activity', now()->timestamp);

        if (! $user->hasTwoFactorEnabled()) {
            if (! $request->routeIs('admin.two-factor.setup*')) {
                return redirect()->route('admin.two-factor.setup');
            }
        } elseif (! $session->get('admin.2fa_passed') && ! $request->routeIs('admin.two-factor.challenge*')) {
            return redirect()->route('admin.two-factor.challenge');
        }

        return $next($request);
    }

    private function signOut(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', $message);
    }
}
