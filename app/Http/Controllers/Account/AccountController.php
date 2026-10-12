<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use App\Models\Member;
use App\Models\Ticket;
use App\Services\DeletionService;
use App\Services\TicketAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** The signed-in member's own page: their conversations and account settings. */
class AccountController extends Controller
{
    public function index(TicketAccess $access): View
    {
        $member = $this->member();

        $tickets = $member->tickets()
            ->whereNull('blocked_at')
            ->with(['messages' => fn ($q) => $q->where('sender', 'user')->limit(1)])
            ->withExists(['deletionRequests as deletion_pending' => fn ($q) => $q->where('status', DeletionRequest::STATUS_PENDING)])
            ->orderByDesc('last_activity_at')
            ->get();

        $current = $access->current();

        return view('public.account.index', [
            'member' => $member,
            'tickets' => $tickets,
            // An anonymous conversation open in this session can be saved to the account.
            'unsavedTicket' => $current && $current->member_id === null ? $current : null,
        ]);
    }

    /** Opens one of the member's conversations. Ownership is checked on the server. */
    public function open(string $reference, TicketAccess $access): RedirectResponse
    {
        $ticket = $this->member()->tickets()->where('reference', $reference)->whereNull('blocked_at')->firstOrFail();

        $ticket->forceFill(['last_activity_at' => now()])->save();
        $ticket->increment('return_visits');
        $access->grant($ticket);

        return redirect()->route('conversation.show');
    }

    /** Saves the conversation open in this session to the account. */
    public function saveCurrent(TicketAccess $access): RedirectResponse
    {
        $ticket = $access->current();

        if (! $ticket) {
            return redirect()->route('account.index');
        }

        if (! self::attach($ticket, $this->member())) {
            abort(403);
        }

        return back()->with('status', __('account.saved_to_account'));
    }

    public function updateName(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:50']]);

        $member = $this->member();
        $member->name = filled($data['name'] ?? null) ? trim($data['name']) : null;
        $member->save();

        return back()->with('status', __('account.name_saved'));
    }

    /**
     * Deletes the account at once. Its conversations go through the same
     * deletion requests as the "Delete my conversation" button, so they are
     * removed by the team under the existing deletion process.
     */
    public function destroy(Request $request, DeletionService $deletions, TicketAccess $access): RedirectResponse
    {
        $member = $this->member();

        $request->validate([
            'confirm_delete' => ['accepted'],
            'password' => $member->hasPassword() ? ['required', 'string'] : ['nullable'],
        ], ['confirm_delete.accepted' => __('account.delete_confirm_required')]);

        if ($member->hasPassword() && ! Hash::check((string) $request->input('password'), $member->password)) {
            return back()->withErrors(['password' => __('account.wrong_password')]);
        }

        DB::transaction(function () use ($member, $deletions) {
            foreach ($member->tickets as $ticket) {
                $deletions->request($ticket);
            }
            $member->delete();
        });

        Auth::guard('member')->logout();
        $access->revoke();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('account.deleted'));
    }

    /**
     * Saves a conversation to an account, if it is not already someone
     * else's. Only verified accounts can hold conversations.
     */
    public static function attach(Ticket $ticket, Member $member): bool
    {
        if (! $member->hasVerifiedEmail()) {
            return false;
        }
        if ($ticket->member_id !== null) {
            return $ticket->member_id === $member->id;
        }

        $ticket->forceFill(['member_id' => $member->id])->save();

        return true;
    }

    private function member(): Member
    {
        return Auth::guard('member')->user();
    }
}
