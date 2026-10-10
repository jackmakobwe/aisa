<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Every dashboard account must set up an authenticator app before it can
 * see anything, and pass a code in every new session.
 */
class TwoFactorController extends Controller
{
    private const PENDING = 'admin.2fa_pending_secret';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogger $audit,
    ) {}

    public function setup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('admin.dashboard');
        }

        $secret = $request->session()->get(self::PENDING) ?? $this->twoFactor->newSecret();
        $request->session()->put(self::PENDING, $secret);

        return view('admin.auth.two-factor-setup', [
            'secret' => $secret,
            'qr' => $this->twoFactor->qrSvg($user, $secret),
        ]);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get(self::PENDING);
        $request->validate(['one_time_password' => ['required', 'string']]);

        if (! $secret || ! $this->twoFactor->verify($secret, $request->input('one_time_password'), $user->id)) {
            throw ValidationException::withMessages(['one_time_password' => 'That code did not match. Check the time on your phone and try again.']);
        }

        [$plain, $hashes] = $this->twoFactor->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(self::PENDING);
        $request->session()->put('admin.2fa_passed', true);
        $this->audit->log('auth.2fa_enabled', $user);

        return view('admin.auth.recovery-codes', ['codes' => $plain]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('admin.2fa_passed')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate([
            'one_time_password' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'required_without:one_time_password'],
        ]);

        $key = 'admin-2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['one_time_password' => 'Too many attempts. Try again in a few minutes.']);
        }

        $ok = $request->filled('one_time_password')
            ? $this->twoFactor->verify($user->two_factor_secret, $request->input('one_time_password'), $user->id)
            : $this->twoFactor->useRecoveryCode($user, $request->input('recovery_code'));

        if (! $ok) {
            RateLimiter::hit($key, 300);
            $this->audit->log('auth.2fa_failed', $user);

            throw ValidationException::withMessages(['one_time_password' => 'That code was not accepted.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('admin.2fa_passed', true);
        $this->audit->log($request->filled('recovery_code') ? 'auth.2fa_recovery_used' : 'auth.2fa_passed', $user);

        return redirect()->intended(route('admin.dashboard'));
    }
}
