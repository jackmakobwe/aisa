<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): View
    {
        return view('admin.account', [
            'user' => $request->user(),
            'recoveryLeft' => count($request->user()->two_factor_recovery_codes ?? []),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill(['password' => $data['password']])->save();
        $this->audit->log('auth.password_changed', $request->user());

        return back()->with('status', 'Password changed.');
    }

    public function recoveryCodes(Request $request, TwoFactorService $twoFactor): View
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        [$plain, $hashes] = $twoFactor->newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $hashes])->save();
        $this->audit->log('auth.recovery_codes_regenerated', $request->user());

        return view('admin.auth.recovery-codes', ['codes' => $plain]);
    }
}
