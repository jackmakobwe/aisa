<?php

namespace App\Http\Middleware;

use App\Services\TicketAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the ticket this session may access and exposes it as the
 * "ticket" request attribute. Without access, the person is sent to the
 * code page. Every ticket route goes through this check on the server.
 */
class RequireTicketAccess
{
    public function __construct(private readonly TicketAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ticket = $this->access->current();

        if (! $ticket) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ticket.session_ended')], 401);
            }

            return redirect()->route('check.show')->with('status', __('ticket.session_ended'));
        }

        $request->attributes->set('ticket', $ticket);

        return $next($request);
    }
}
