<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Which ticket the current browser session may read.
 *
 * Access is held server-side in the (encrypted) session and is granted only
 * by starting a conversation or entering a valid code. Ticket ids never
 * appear in public URLs, so there is nothing to guess or tamper with.
 */
class TicketAccess
{
    private const SESSION_KEY = 'aisa.ticket_id';

    public function __construct(
        private readonly Request $request,
        private readonly TicketCredentialService $credentials,
    ) {}

    public function grant(Ticket $ticket): void
    {
        // New session id on every grant: protects against session fixation.
        $this->request->session()->regenerate();
        $this->request->session()->put(self::SESSION_KEY, $ticket->id);
    }

    public function current(): ?Ticket
    {
        $id = $this->request->session()->get(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $ticket = Ticket::find($id);
        if (! $ticket || $ticket->isBlocked()) {
            $this->revoke();

            return null;
        }

        return $ticket;
    }

    public function revoke(): void
    {
        $this->request->session()->forget([self::SESSION_KEY, 'aisa.issued_code']);
    }

    /** The code remembered on this device, if the person opted in. */
    public function rememberedCode(): ?string
    {
        $code = $this->request->cookie(config('aisa.tickets.remember_cookie'));

        return is_string($code) && $this->credentials->normalize($code) !== null ? $code : null;
    }

    public function rememberCookie(string $code): SymfonyCookie
    {
        // Encrypted by the EncryptCookies middleware, HTTP-only, same-site.
        return Cookie::make(
            config('aisa.tickets.remember_cookie'),
            $code,
            config('aisa.tickets.remember_days') * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(config('aisa.tickets.remember_cookie'));
    }
}
