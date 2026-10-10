<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Staff accounts. There is no public registration: only an admin can add
 * a responder, and every new account must set up two-factor sign-in.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_RESPONDER])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data);
        $this->audit->log('user.created', $user, ['role' => $data['role']]);

        return back()->with('status', "Account created for {$user->name}. Share the password privately; they will set up two-factor sign-in on first login.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        $this->audit->log($user->is_active ? 'user.activated' : 'user.deactivated', $user);

        return back()->with('status', $user->is_active ? 'Account activated.' : 'Account deactivated. Any open session ends on its next request.');
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit->log('user.2fa_reset', $user);

        return back()->with('status', "Two-factor sign-in reset for {$user->name}. They must set it up again at next login.");
    }
}
