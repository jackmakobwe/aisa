<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Account\AccountController;
use App\Http\Requests\ChooseHelpRequest;
use App\Models\Ticket;
use App\Services\ConversationService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChooseHelpController extends Controller
{
    public function show(Request $request, SettingsService $settings): View
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');

        return view('public.choose-help', [
            'ticket' => $ticket,
            'humanOpen' => $settings->humanRequestsOpen() || $ticket->safety_flagged,
        ]);
    }

    public function store(ChooseHelpRequest $request, ConversationService $conversations): RedirectResponse
    {
        /** @var Ticket $ticket */
        $ticket = $request->attributes->get('ticket');
        $mode = $request->validated('mode');

        if (! $conversations->chooseHelp($ticket, $mode)) {
            return back()->with('status', __('help.paused'));
        }

        // Signed in: save the conversation to the account, so Aisa's reply appears there.
        if ($member = Auth::guard('member')->user()) {
            AccountController::attach($ticket, $member);
        }

        // Anyone else asking for Aisa needs a code to come back for her reply.
        if ($mode === 'human' && $ticket->member_id === null && ($code = $conversations->issueCodeIfMissing($ticket))) {
            $request->session()->put('aisa.issued_code', $code);

            return redirect()->route('code.issued');
        }

        $status = match (true) {
            $mode !== 'human' => __('help.ai_chosen'),
            $ticket->member_id !== null => __('account.human_requested'),
            default => __('help.human_requested'),
        };

        return redirect()->route('conversation.show')->with('status', $status);
    }
}
