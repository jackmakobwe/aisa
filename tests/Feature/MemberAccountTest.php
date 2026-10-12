<?php

namespace Tests\Feature;

use App\Models\DeletionRequest;
use App\Models\Member;
use App\Models\Ticket;
use App\Notifications\MemberResetPassword;
use App\Notifications\MemberVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'quiet-river-2048';

    public function test_sign_in_and_register_pages_render_in_both_languages(): void
    {
        foreach (['en' => ['Welcome back', 'Create your account'], 'sw' => ['Karibu tena', 'Fungua akaunti yako']] as $locale => [$login, $register]) {
            $this->withSession(['locale' => $locale])->get(route('account.login'))->assertOk()->assertSee($login);
            $this->withSession(['locale' => $locale])->get(route('account.register'))->assertOk()->assertSee($register);
        }
    }

    public function test_registering_stores_the_email_encrypted_and_sends_a_neutral_confirmation(): void
    {
        Notification::fake();

        $this->post(route('account.register.store'), $this->registration('Amina@Example.org'))
            ->assertRedirect(route('account.check-email'));

        $member = Member::sole();
        $this->assertSame('amina@example.org', $member->email);
        $this->assertSame(Member::emailHash('amina@example.org'), $member->email_hash);
        $this->assertDatabaseMissing('members', ['email' => 'amina@example.org']);
        $this->assertStringNotContainsString('amina', (string) DB::table('members')->value('email'));
        $this->assertNull($member->email_verified_at);
        $this->assertFalse($this->isAuthenticated('member'), 'Registering does not sign in until the email is confirmed.');

        Notification::assertSentTo($member, MemberVerifyEmail::class, function (MemberVerifyEmail $n) use ($member) {
            $mail = $n->toMail($member);

            return $mail->subject === 'Confirm your email address'
                && ! str_contains(strtolower(implode(' ', $mail->introLines)), 'aisa');
        });
    }

    public function test_registering_an_existing_email_reveals_nothing_and_creates_nothing(): void
    {
        $this->member('amina@example.org');
        Notification::fake();

        $this->post(route('account.register.store'), $this->registration('amina@example.org'))
            ->assertRedirect(route('account.check-email'));

        $this->assertSame(1, Member::count());
        Notification::assertNothingSent();
    }

    public function test_registration_requires_terms_and_a_reasonable_password(): void
    {
        $this->post(route('account.register.store'), ['email' => 'a@example.org', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors(['password', 'terms']);
        $this->assertSame(0, Member::count());
    }

    public function test_confirmation_link_verifies_the_email_and_tampered_links_fail(): void
    {
        $member = $this->member('amina@example.org', verified: false);

        $bad = URL::temporarySignedRoute('account.verify', now()->addHour(), ['id' => $member->id, 'hash' => sha1('someone@else.org')]);
        $this->get($bad)->assertRedirect(route('account.login'));
        $this->assertFalse($member->fresh()->hasVerifiedEmail());

        $unsigned = route('account.verify', ['id' => $member->id, 'hash' => sha1('amina@example.org')]);
        $this->get($unsigned)->assertForbidden();

        $good = URL::temporarySignedRoute('account.verify', now()->addHour(), ['id' => $member->id, 'hash' => sha1('amina@example.org')]);
        $this->get($good)->assertRedirect(route('account.login'))->assertSessionHas('status');
        $this->assertTrue($member->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_members_can_sign_in_but_not_use_the_account_yet(): void
    {
        $this->member('amina@example.org', verified: false);

        $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => self::PASSWORD])
            ->assertRedirect(route('account.index'));
        $this->get(route('account.index'))->assertRedirect(route('account.verify.notice'));
        $this->get(route('account.verify.notice'))->assertOk()->assertSee('Please confirm your email address');
    }

    public function test_sign_in_with_wrong_password_is_generic_and_locks_after_five_tries(): void
    {
        $this->member('amina@example.org');

        $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => 'wrong-password-1'])
            ->assertSessionHasErrors(['email' => 'The email or password is not right. Please try again.']);
        $this->post(route('account.login.store'), ['email' => 'nobody@example.org', 'password' => 'wrong-password-1'])
            ->assertSessionHasErrors(['email' => 'The email or password is not right. Please try again.']);

        foreach (range(1, 4) as $i) {
            $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => "wrong-password-{$i}"]);
        }

        $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertFalse($this->isAuthenticated('member'));
    }

    public function test_signed_in_member_conversation_is_saved_to_the_account_and_needs_no_code(): void
    {
        $this->fakeAi();
        $member = $this->member('amina@example.org');
        $this->actingAs($member, 'member');

        $ticket = $this->startConversation('I want to talk to Aisa about my family.');
        $this->assertSame($member->id, $ticket->member_id);

        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1'])
            ->assertRedirect(route('conversation.show'))
            ->assertSessionHas('status', 'Your request has been sent to Aisa. Her reply will appear in My account.');

        $this->assertFalse($ticket->fresh()->hasCode());
        $this->assertSame(Ticket::STATUS_AWAITING_HUMAN, $ticket->fresh()->status);

        $this->get(route('account.index'))->assertOk()
            ->assertSee('Waiting for Aisa')
            ->assertSee('I want to talk to Aisa about my family.');
    }

    public function test_anonymous_flow_still_issues_a_code_for_talk_to_aisa(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();

        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1'])->assertRedirect(route('code.issued'));
        $this->assertTrue($ticket->fresh()->hasCode());
        $this->assertNull($ticket->fresh()->member_id);
    }

    public function test_member_can_open_only_their_own_conversations(): void
    {
        $owner = $this->member('owner@example.org');
        $other = $this->member('other@example.org');
        $mine = $this->startConversationDirectly();
        $mine->forceFill(['member_id' => $owner->id])->save();
        $theirs = $this->startConversationDirectly();
        $theirs->forceFill(['member_id' => $other->id])->save();

        $this->actingAs($owner, 'member');
        $this->post(route('account.open', $theirs->reference))->assertNotFound();
        $this->get(route('conversation.show'))->assertRedirect(route('check.show'));

        $this->post(route('account.open', $mine->reference))->assertRedirect(route('conversation.show'));
        $this->get(route('conversation.show'))->assertOk();
    }

    public function test_open_anonymous_conversation_can_be_saved_but_another_members_cannot_be_taken(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();
        $member = $this->member('amina@example.org');
        $this->actingAs($member, 'member');

        $this->get(route('account.index'))->assertSee('Save your current conversation?');
        $this->post(route('account.save-current'))->assertRedirect();
        $this->assertSame($member->id, $ticket->fresh()->member_id);

        $intruder = $this->member('intruder@example.org');
        $this->actingAs($intruder, 'member');
        $this->post(route('account.save-current'))->assertForbidden();
        $this->assertSame($member->id, $ticket->fresh()->member_id);
    }

    public function test_unverified_member_cannot_hold_conversations(): void
    {
        $this->fakeAi();
        $member = $this->member('amina@example.org', verified: false);
        $this->actingAs($member, 'member');

        $ticket = $this->startConversation();
        $this->assertNull($ticket->member_id);
    }

    public function test_password_reset_uses_the_email_hash_and_changes_the_password(): void
    {
        Notification::fake();
        $member = $this->member('amina@example.org');

        $this->post(route('account.password.email'), ['email' => 'amina@example.org'])->assertSessionHas('status');
        $this->post(route('account.password.email'), ['email' => 'nobody@example.org'])->assertSessionHas('status');

        $this->assertSame($member->email_hash, DB::table('member_password_reset_tokens')->value('email'));
        $this->assertDatabaseMissing('member_password_reset_tokens', ['email' => 'amina@example.org']);

        $token = null;
        Notification::assertSentTo($member, MemberResetPassword::class, function (MemberResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post(route('account.password.update'), [
            'token' => 'not-the-token', 'email' => 'amina@example.org',
            'password' => 'new-password-77', 'password_confirmation' => 'new-password-77',
        ])->assertSessionHasErrors('email');

        $this->post(route('account.password.update'), [
            'token' => $token, 'email' => 'amina@example.org',
            'password' => 'new-password-77', 'password_confirmation' => 'new-password-77',
        ])->assertRedirect(route('account.login'));

        $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => 'new-password-77'])
            ->assertRedirect(route('account.index'));
    }

    public function test_google_is_hidden_and_unreachable_until_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('account.login'))->assertDontSee('Continue with Google');
        $this->get(route('account.google'))->assertNotFound();
        $this->get(route('account.google.callback'))->assertNotFound();
    }

    public function test_google_sign_in_creates_a_verified_account_without_storing_the_google_name(): void
    {
        $this->configureGoogle();
        $this->mockGoogleUser('g-123', 'Amina@Gmail.com', verified: true, name: 'Amina Juma');

        $this->get(route('account.login'))->assertSee('Continue with Google');
        $this->get(route('account.google.callback').'?code=x&state=y')->assertRedirect(route('account.index'));

        $member = Member::sole();
        $this->assertSame('amina@gmail.com', $member->email);
        $this->assertTrue($member->hasVerifiedEmail());
        $this->assertNull($member->name);
        $this->assertNull($member->password);
        $this->assertSame($member->id, Member::findByGoogleId('g-123')?->id);
        $this->assertStringNotContainsString('g-123', (string) DB::table('members')->value('google_id_hash'));
        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_google_sign_in_with_unconfirmed_google_email_is_refused(): void
    {
        $this->configureGoogle();
        $this->mockGoogleUser('g-123', 'amina@gmail.com', verified: false);

        $this->get(route('account.google.callback').'?code=x&state=y')->assertRedirect(route('account.login'));
        $this->assertSame(0, Member::count());
    }

    public function test_google_takes_over_an_unconfirmed_signup_and_discards_its_password(): void
    {
        // Someone else registered this address with a password but never confirmed it.
        $squatter = $this->member('amina@gmail.com', verified: false);
        $this->configureGoogle();
        $this->mockGoogleUser('g-123', 'amina@gmail.com', verified: true);

        $this->get(route('account.google.callback').'?code=x&state=y')->assertRedirect(route('account.index'));

        $member = $squatter->fresh();
        $this->assertNull($member->password, 'The unconfirmed password must not keep working.');
        $this->assertTrue($member->hasVerifiedEmail());
    }

    public function test_member_session_never_reaches_the_staff_dashboard(): void
    {
        $this->actingAs($this->member('amina@example.org'), 'member');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_deleting_the_account_requests_deletion_of_its_conversations(): void
    {
        $member = $this->member('amina@example.org');
        $ticket = $this->startConversationDirectly();
        $ticket->forceFill(['member_id' => $member->id])->save();
        $this->actingAs($member, 'member');

        $this->post(route('account.destroy'), ['confirm_delete' => '1', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotNull($member->fresh());

        $this->post(route('account.destroy'), ['confirm_delete' => '1', 'password' => self::PASSWORD])->assertRedirect(route('home'));

        $this->assertNull($member->fresh());
        $this->assertNull($ticket->fresh()->member_id);
        $this->assertSame(DeletionRequest::STATUS_PENDING, $ticket->fresh()->deletionRequests()->sole()->status);
        $this->assertFalse($this->isAuthenticated('member'));
    }

    public function test_sign_out_and_quick_exit_end_the_member_session(): void
    {
        $member = $this->member('amina@example.org');

        $this->actingAs($member, 'member')->post(route('account.logout'))->assertRedirect(route('home'));
        $this->assertFalse($this->isAuthenticated('member'));

        $this->post(route('account.login.store'), ['email' => 'amina@example.org', 'password' => self::PASSWORD]);
        $this->assertTrue($this->isAuthenticated('member'));
        $this->post(route('quick-exit'))->assertNoContent();
        $this->get(route('account.index'))->assertRedirect(route('account.login'));
    }

    public function test_header_shows_sign_in_or_my_account(): void
    {
        $this->get('/')->assertSee(route('account.login'), false)->assertSee('Sign in');

        $this->actingAs($this->member('amina@example.org'), 'member');
        $this->get('/')->assertSee(route('account.index'), false)->assertSee('My account');
    }

    public function test_prune_removes_old_unconfirmed_accounts_only(): void
    {
        $old = $this->member('old@example.org', verified: false);
        $old->forceFill(['created_at' => now()->subDays(8)])->save();
        $recent = $this->member('recent@example.org', verified: false);
        $confirmed = $this->member('confirmed@example.org');
        $confirmed->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->artisan('aisa:prune')->assertSuccessful();

        $this->assertNull($old->fresh());
        $this->assertNotNull($recent->fresh());
        $this->assertNotNull($confirmed->fresh());
    }

    private function member(string $email, bool $verified = true): Member
    {
        $member = (new Member(['locale' => 'en']))->setEmail($email);
        $member->password = self::PASSWORD;
        $member->email_verified_at = $verified ? now() : null;
        $member->save();

        return $member;
    }

    private function registration(string $email): array
    {
        return ['email' => $email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => '1'];
    }

    private function configureGoogle(): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function mockGoogleUser(string $id, string $email, bool $verified, ?string $name = null): void
    {
        $user = (new GoogleUser)->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
