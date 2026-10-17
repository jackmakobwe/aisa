<?php

namespace Tests\Feature;

use App\Models\CrisisResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pages(): array
    {
        return [['home'], ['how'], ['about'], ['mama-care'], ['get-help'], ['privacy'], ['terms'], ['contact.show'], ['check.show'], ['not-open'], ['under-18'], ['articles.index']];
    }

    #[DataProvider('pages')]
    public function test_every_public_page_renders_in_both_languages_with_safety_controls(string $route): void
    {
        foreach (['en' => 'Quick exit', 'sw' => 'Toka haraka'] as $locale => $quickExit) {
            $response = $this->withSession(['locale' => $locale])->get(route($route));

            $response->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee($quickExit)
                ->assertSee(route('get-help'), false);
        }
    }

    public function test_home_shows_the_welcome_question_and_disclaimer(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('What would you like to talk about?')
            ->assertSee('Aisa is not an emergency service');

        $this->withSession(['locale' => 'sw'])->get('/')
            ->assertSee('Ni jambo gani ungependa kuzungumzia?');
    }

    public function test_language_switch_is_remembered_in_session_and_cookie(): void
    {
        $this->get(route('locale', 'sw'))->assertRedirect()->assertCookie('aisa_locale', 'sw', false);
        $this->get('/')->assertSee('Anza mazungumzo');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->get('/language/fr')->assertNotFound();
    }

    public function test_get_help_shows_only_verified_contacts_and_never_invents_numbers(): void
    {
        $unverified = CrisisResource::create(['category' => 'emergency', 'name' => 'Unchecked Line', 'phone' => '000 111']);
        $verified = CrisisResource::create(['category' => 'gbv', 'name' => 'Checked Desk', 'phone' => '000 222']);
        $verified->forceFill(['is_verified' => true, 'verified_at' => now()])->save();

        $this->get(route('get-help'))
            ->assertOk()
            ->assertSee('Checked Desk')
            ->assertDontSee('Unchecked Line')
            // Empty sections are not listed one by one any more.
            ->assertDontSee('still being checked');

        // With nothing verified at all, one short message instead of empty sections.
        $verified->delete();
        $this->get(route('get-help'))->assertOk()->assertSee('still being checked');
    }

    public function test_development_banner_says_ai_is_simulated(): void
    {
        $this->get('/')->assertSee('AI replies are simulated');
    }

    public function test_security_headers_and_no_store_cache_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_legal_pages_are_marked_as_drafts(): void
    {
        $this->get(route('privacy'))->assertSee('waiting for review by a qualified Tanzanian legal adviser');
        $this->get(route('terms'))->assertSee('waiting for review by a qualified Tanzanian legal adviser');
    }

    public function test_contact_form_stores_an_encrypted_submission_and_rejects_bots(): void
    {
        $this->post(route('contact.store'), ['type' => 'feedback', 'message' => 'The site is easy to use.', 'privacy_ok' => '1'])
            ->assertRedirect(route('contact.show'));

        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertDatabaseMissing('contact_submissions', ['message' => 'The site is easy to use.']);

        $this->post(route('contact.store'), ['type' => 'feedback', 'message' => 'spam spam', 'privacy_ok' => '1', 'website' => 'http://spam'])
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        foreach (range(1, config('aisa.limits.contact_per_hour')) as $i) {
            $this->post(route('contact.store'), ['type' => 'feedback', 'message' => "Message number {$i}", 'privacy_ok' => '1']);
        }

        $this->post(route('contact.store'), ['type' => 'feedback', 'message' => 'One too many', 'privacy_ok' => '1'])->assertStatus(429);
    }
}
