<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Account\AccountController;
use App\Http\Requests\ConsentRequest;
use App\Http\Requests\UserMessageRequest;
use App\Services\ConversationService;
use App\Services\TicketAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * First message, then consent and age, then the conversation. The message
 * waits in the encrypted server-side session and is not processed (or sent
 * to the AI) until the person agrees.
 */
class ConsentController extends Controller
{
    private const DRAFT_KEY = 'aisa.draft';

    public function start(UserMessageRequest $request): RedirectResponse
    {
        $request->session()->put(self::DRAFT_KEY, $request->validated('message'));

        return redirect()->route('consent.show');
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::DRAFT_KEY)) {
            return redirect()->route('home');
        }

        return view('public.consent', [
            'minorPolicy' => config('aisa.minor_policy'),
        ]);
    }

    /** Declining discards the waiting message; nothing is stored. */
    public function decline(Request $request): RedirectResponse
    {
        $request->session()->forget(self::DRAFT_KEY);

        return redirect()->route('home')->with('status', __('consent.declined'));
    }

    public function store(ConsentRequest $request, ConversationService $conversations, TicketAccess $access): RedirectResponse
    {
        $draft = $request->session()->get(self::DRAFT_KEY);
        if (! is_string($draft) || $draft === '') {
            return redirect()->route('home');
        }

        $ageGroup = $request->validated('age_group');
        if ($ageGroup === 'minor' && config('aisa.minor_policy') === 'block') {
            $request->session()->forget(self::DRAFT_KEY);

            return redirect()->route('under-18');
        }

        [$ticket, $report] = $conversations->start(app()->getLocale(), $ageGroup, $draft);

        if ($name = trim((string) $request->validated('display_name'))) {
            $ticket->forceFill(['display_name' => $name])->save();
        }

        // Signed in with a confirmed email: the conversation is saved to the account.
        if ($member = Auth::guard('member')->user()) {
            AccountController::attach($ticket, $member);
        }

        $request->session()->forget(self::DRAFT_KEY);
        $access->grant($ticket);

        $redirect = redirect()->route('conversation.show');

        return $report->aiError ? $redirect->with('ai_error', true) : $redirect;
    }
}
