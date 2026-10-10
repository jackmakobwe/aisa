<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['admin.dashboard', 'admin.conversations.index', 'admin.safety.index', 'admin.settings', 'admin.audit'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Password-123']);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
    }

    public function test_login_attempts_are_limited(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Password-123']);
        foreach (range(1, config('aisa.admin.max_login_attempts')) as $i) {
            $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Password-123', 'is_active' => false]);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123']);
        $this->assertGuest();
    }

    public function test_new_accounts_must_set_up_two_factor_before_seeing_anything(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Password-123']);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.setup'));
        $this->get(route('admin.conversations.index'))->assertRedirect(route('admin.two-factor.setup'));

        $this->get(route('admin.two-factor.setup'))->assertOk()->assertSee('Set up two-factor sign-in');
        $secret = session('admin.2fa_pending_secret');

        $this->post(route('admin.two-factor.setup.confirm'), ['one_time_password' => '000000'])->assertSessionHasErrors('one_time_password');

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('admin.two-factor.setup.confirm'), ['one_time_password' => $code])->assertOk()->assertSee('Save your recovery codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_existing_two_factor_must_be_passed_every_session(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create(['password' => 'Correct-Password-123']);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123']);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.challenge'));

        $this->post(route('admin.two-factor.challenge.verify'), ['one_time_password' => '123456'])->assertSessionHasErrors();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.challenge'));

        $code = (new Google2FA)->getCurrentOtp(self::SECRET);
        $this->post(route('admin.two-factor.challenge.verify'), ['one_time_password' => $code])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.2fa_passed', 'user_id' => $user->id]);
    }

    public function test_a_totp_code_cannot_be_replayed(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create(['password' => 'Correct-Password-123']);
        $code = (new Google2FA)->getCurrentOtp(self::SECRET);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123']);
        $this->post(route('admin.two-factor.challenge.verify'), ['one_time_password' => $code])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123']);
        $this->post(route('admin.two-factor.challenge.verify'), ['one_time_password' => $code])->assertSessionHasErrors();
    }

    public function test_recovery_code_works_once(): void
    {
        $user = User::factory()->withTwoFactor(self::SECRET)->create(['password' => 'Correct-Password-123']);

        $this->post(route('admin.login.attempt'), ['email' => $user->email, 'password' => 'Correct-Password-123']);
        $this->post(route('admin.two-factor.challenge.verify'), ['recovery_code' => 'recov-ery01'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_idle_sessions_are_signed_out(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $this->actingAs($user)->withSession([
            'admin.2fa_passed' => true,
            'admin.last_activity' => now()->subMinutes(config('aisa.admin.idle_minutes') + 1)->timestamp,
        ]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_deactivating_a_user_ends_their_session(): void
    {
        $user = $this->actingAsStaff();
        $user->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_responders_cannot_use_admin_only_areas(): void
    {
        $this->actingAsStaff(User::factory()->withTwoFactor()->create());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.safety.index'))->assertOk();
        foreach (['admin.settings', 'admin.users.index', 'admin.audit', 'admin.resources.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_password_reset_does_not_reveal_whether_an_account_exists(): void
    {
        $user = User::factory()->create();

        $known = $this->post(route('admin.password.email'), ['email' => $user->email]);
        $unknown = $this->post(route('admin.password.email'), ['email' => 'nobody@example.org']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_admins_can_create_responders_with_strong_passwords_only(): void
    {
        $this->actingAsStaff();

        $this->post(route('admin.users.store'), ['name' => 'Backup', 'email' => 'backup@aisa.test', 'role' => 'responder', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');

        $this->post(route('admin.users.store'), ['name' => 'Backup', 'email' => 'backup@aisa.test', 'role' => 'responder', 'password' => 'Long-Enough-Pass1', 'password_confirmation' => 'Long-Enough-Pass1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'backup@aisa.test', 'role' => 'responder']);
        $this->assertTrue(AuditLog::where('action', 'user.created')->exists());
    }
}
