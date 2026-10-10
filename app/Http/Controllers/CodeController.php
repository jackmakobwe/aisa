<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\ConversationService;
use App\Services\TicketAccess;
use App\Services\TicketCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Issuing ticket codes and returning with them.
 *
 * Failed lookups are counted per IP (hashed) and lock the form for a while
 * after a few attempts. Every failure shows the same message, whether the
 * code was malformed, unknown or blocked, so the form cannot be used to
 * learn whether a code exists.
 */
class CodeController extends Controller
{
    public function __construct(
        private readonly TicketAccess $access,
        private readonly TicketCredentialService $credentials,
    ) {}

    public function show(): View
    {
        return view('public.check', ['remembered' => $this->access->rememberedCode() !== null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:64']]);

        return $this->attempt($request, (string) $request->input('code'));
    }

    public function remembered(Request $request): RedirectResponse
    {
        $code = $this->access->rememberedCode();
        if (! $code) {
            return redirect()->route('check.show');
        }

        $response = $this->attempt($request, $code);

        // A remembered code that no longer works is forgotten.
        return $request->session()->has('errors') ? $response->withCookie($this->access->forgetCookie()) : $response;
    }

    public function forgetDevice(): RedirectResponse
    {
        return redirect()->route('check.show')
            ->with('status', __('ticket.device_forgotten'))
            ->withCookie($this->access->forgetCookie());
    }

    /** Show a newly issued code, once. */
    public function issued(Request $request): View|RedirectResponse
    {
        $code = $request->session()->get('aisa.issued_code');
        if (! $code) {
            return redirect()->route('conversation.show');
        }

        return view('public.your-code', ['code' => $code, 'ticket' => $request->attributes->get('ticket')]);
    }

    /** Issue a code to someone continuing with AI who wants to come back later. */
    public function issue(Request $request, ConversationService $conversations): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $code = $conversations->issueCodeIfMissing($ticket);

        if (! $code) {
            return redirect()->route('conversation.show')->with('status', __('ticket.already_issued'));
        }

        $request->session()->put('aisa.issued_code', $code);

        return redirect()->route('code.issued');
    }

    public function saved(Request $request): RedirectResponse
    {
        $request->validate(['remember' => ['nullable', 'boolean']]);
        $code = $request->session()->pull('aisa.issued_code');
        $response = redirect()->route('conversation.show')->with('status', __('ticket.saved_thanks'));

        return $code && $request->boolean('remember')
            ? $response->withCookie($this->access->rememberCookie($code))
            : $response;
    }

    private function attempt(Request $request, string $code): RedirectResponse
    {
        $key = 'code-fail:'.hash('sha256', (string) $request->ip());
        $max = config('aisa.tickets.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->withErrors(['code' => __('ticket.locked', ['minutes' => $minutes])]);
        }

        $ticket = $this->credentials->find($code);
        if (! $ticket) {
            RateLimiter::hit($key, config('aisa.tickets.lockout_minutes') * 60);

            return back()->withErrors(['code' => __('ticket.not_found')]);
        }

        RateLimiter::clear($key);
        $ticket->forceFill(['last_activity_at' => now()])->save();
        $ticket->increment('return_visits');
        $this->access->grant($ticket);

        return redirect()->route('conversation.show');
    }
}
