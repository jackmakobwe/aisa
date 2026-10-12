<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\TicketAccess;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Optional public accounts: sign in, create an account, confirm the email
 * address, reset a forgotten password, sign out.
 *
 * None of these responses reveal whether an email address has an account:
 * someone checking whether a partner or relative uses Aisa learns nothing.
 */
class MemberAuthController extends Controller
{
    private const MAX_FAILED_LOGINS = 5;

    private const LOCKOUT_SECONDS = 900;

    public static function passwordRule(): PasswordRule
    {
        $rule = PasswordRule::min(10)->letters()->numbers();

        return app()->runningUnitTests() ? $rule : $rule->uncompromised();
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('member')->check()) {
            return redirect()->route('account.index');
        }

        // Coming from an open conversation: return there after signing in.
        $previous = url()->previous();
        if (in_array($previous, [route('help.show'), route('conversation.show')], true)) {
            redirect()->setIntendedUrl($previous);
        }

        return view('public.account.login', ['googleEnabled' => self::googleEnabled()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        $key = 'member-login:'.hash('sha256', Member::emailHash($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_LOGINS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return back()->onlyInput('email')->withErrors(['email' => __('account.locked', ['minutes' => $minutes])]);
        }

        if (! Auth::guard('member')->attempt(['email' => $data['email'], 'password' => $data['password']])) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return back()->onlyInput('email')->withErrors(['email' => __('account.login_failed')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->recordLogin(Auth::guard('member')->user());

        return redirect()->intended(route('account.index'));
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('member')->check()) {
            return redirect()->route('account.index');
        }

        return view('public.account.register', ['googleEnabled' => self::googleEnabled()]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200', 'confirmed', self::passwordRule()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => __('account.terms_required'),
        ]);

        // An existing address gets exactly the same response and no new account.
        if (! Member::findByEmail($data['email'])) {
            $member = new Member(['name' => filled($data['name'] ?? null) ? trim($data['name']) : null, 'locale' => app()->getLocale()]);
            $member->setEmail($data['email']);
            $member->password = $data['password'];
            $member->save();

            $member->sendEmailVerificationNotification();
        }

        return redirect()->route('account.check-email');
    }

    public function checkEmail(): View
    {
        return view('public.account.check-email');
    }

    /** Shown to a signed-in member whose email is not confirmed yet. */
    public function verifyNotice(): View|RedirectResponse
    {
        $member = Auth::guard('member')->user();
        if (! $member) {
            return redirect()->route('account.login');
        }
        if ($member->hasVerifiedEmail()) {
            return redirect()->route('account.index');
        }

        return view('public.account.verify-notice');
    }

    public function resendVerification(): RedirectResponse
    {
        $member = Auth::guard('member')->user();
        if ($member && ! $member->hasVerifiedEmail()) {
            $member->sendEmailVerificationNotification();
        }

        return back()->with('status', __('account.verification_resent'));
    }

    /** The link from the confirmation email (signed and time-limited by the route). */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $member = Member::find($id);

        if (! $member || ! hash_equals(sha1($member->getEmailForVerification()), $hash)) {
            return redirect()->route('account.login')->withErrors(['email' => __('account.verify_invalid')]);
        }

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
        }

        $signedIn = Auth::guard('member')->id() === $member->id;

        return redirect()->route($signedIn ? 'account.index' : 'account.login')->with('status', __('account.verified'));
    }

    public function showForgot(): View
    {
        return view('public.account.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:254']]);

        // The result is ignored on purpose: same message whether or not the address has an account.
        Password::broker('members')->sendResetLink(['email' => $request->input('email')]);

        return back()->with('status', __('account.reset_link_sent'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('public.account.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200', 'confirmed', self::passwordRule()],
        ]);

        $status = Password::broker('members')->reset($data, function (Member $member, string $password) {
            $member->password = $password;
            // Following the emailed link proves the person controls the address.
            if (! $member->hasVerifiedEmail()) {
                $member->email_verified_at = now();
            }
            $member->save();

            event(new PasswordReset($member));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->onlyInput('email')->withErrors(['email' => __('account.reset_invalid')]);
        }

        return redirect()->route('account.login')->with('status', __('account.password_changed'));
    }

    public function logout(Request $request, TicketAccess $access): RedirectResponse
    {
        Auth::guard('member')->logout();
        $access->revoke();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('account.signed_out'));
    }

    public static function googleEnabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public static function recordLogin(Member $member): void
    {
        $member->forceFill(['last_login_at' => now(), 'locale' => app()->getLocale()])->save();
    }
}
