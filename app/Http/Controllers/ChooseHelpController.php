<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChooseHelpRequest;
use App\Models\Ticket;
use App\Services\ConversationService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // Anyone asking for Aisa needs a code to come back for her reply.
        if ($mode === 'human' && ($code = $conversations->issueCodeIfMissing($ticket))) {
            $request->session()->put('aisa.issued_code', $code);

            return redirect()->route('code.issued');
        }

        return redirect()->route('conversation.show')->with('status', $mode === 'human' ? __('help.human_requested') : __('help.ai_chosen'));
    }
}
