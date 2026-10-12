<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continue with Google". Only the Google account id (hashed) and the email
 * address (encrypted) are kept; name and photo are not stored.
 * Hidden, and these routes return 404, until GOOGLE_CLIENT_ID and
 * GOOGLE_CLIENT_SECRET are set.
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(MemberAuthController::googleEnabled(), 404);

        return Socialite::driver('google')->scopes(['openid', 'email'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(MemberAuthController::googleEnabled(), 404);

        if ($request->has('error')) {
            // The person cancelled on Google's screen.
            return redirect()->route('account.login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['exception' => $e::class]);

            return redirect()->route('account.login')->withErrors(['email' => __('account.google_failed')]);
        }

        $email = $google->getEmail();
        $emailVerified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);

        if (! $google->getId() || ! $email || ! $emailVerified) {
            return redirect()->route('account.login')->withErrors(['email' => __('account.google_unverified')]);
        }

        $member = Member::findByGoogleId((string) $google->getId());

        if (! $member) {
            // Google has confirmed the address, so an existing account with it is linked.
            $member = Member::findByEmail($email) ?? (new Member(['locale' => app()->getLocale()]))->setEmail($email);
            $member->linkGoogle((string) $google->getId());
            if (! $member->hasVerifiedEmail()) {
                // A password set by someone who never confirmed this address is
                // discarded, so nobody can pre-register an account and take it
                // over once the real owner signs in with Google.
                $member->password = null;
                $member->email_verified_at = now();
            }
            $member->save();
        }

        Auth::guard('member')->login($member);
        $request->session()->regenerate();
        MemberAuthController::recordLogin($member);

        return redirect()->intended(route('account.index'));
    }
}
