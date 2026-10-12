<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account pages need a signed-in member with a confirmed email address.
 * Uses the "member" guard only; staff sessions never count.
 */
class RequireMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if (! $member) {
            return redirect()->guest(route('account.login'));
        }

        if (! $member->hasVerifiedEmail()) {
            return redirect()->route('account.verify.notice');
        }

        return $next($request);
    }
}
