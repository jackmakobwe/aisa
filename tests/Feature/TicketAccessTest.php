<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\TicketCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    private function ticketWithCode(string $message = 'Hello'): array
    {
        $this->fakeAi();
        $ticket = $this->startConversationDirectly($message);
        $code = app(TicketCredentialService::class)->issue($ticket);

        return [$ticket->fresh(), $code];
    }

    public function test_plaintext_code_is_never_stored(): void
    {
        [$ticket, $code] = $this->ticketWithCode();
        $normalized = str_replace('-', '', $code);

        foreach (['tickets', 'ai_analyses', 'audit_logs', 'sessions'] as $table) {
            $dump = json_encode(\DB::table($table)->get());
            $this->assertStringNotContainsString($normalized, $dump, "Code found in {$table}");
            $this->assertStringNotContainsString($code, $dump, "Code found in {$table}");
        }
        $this->assertSame(64, strlen($ticket->code_hash));
    }

    public function test_return_visit_with_code_in_any_format(): void
    {
        [$ticket, $code] = $this->ticketWithCode('My returning message');

        $this->post(route('check.store'), ['code' => strtolower(str_replace('-', ' ', $code))])->assertRedirect(route('conversation.show'));
        $this->get(route('conversation.show'))->assertOk()->assertSee('My returning message');
        $this->assertSame(1, $ticket->fresh()->return_visits);
    }

    public function test_wrong_malformed_and_blocked_codes_get_the_same_generic_message(): void
    {
        [$ticket, $code] = $this->ticketWithCode();
        $ticket->forceFill(['blocked_at' => now()])->save();
        $generic = __('ticket.not_found');

        foreach (['AISA4821', 'ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ', $code] as $attempt) {
            $this->from(route('check.show'))->post(route('check.store'), ['code' => $attempt])
                ->assertRedirect(route('check.show'))
                ->assertSessionHasErrors(['code' => $generic]);
        }
    }

    public function test_repeated_guessing_locks_the_form_even_for_a_valid_code(): void
    {
        [, $code] = $this->ticketWithCode();

        foreach (range(1, config('aisa.tickets.max_attempts')) as $i) {
            $this->post(route('check.store'), ['code' => 'ABCD-EFGH-JKMN-PQRS-TVWX-YZ01-23'.$i.'4']);
        }

        $this->post(route('check.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('conversation.show'))->assertRedirect(route('check.show'));
    }

    public function test_one_session_cannot_reach_another_ticket(): void
    {
        [$ticketA] = $this->ticketWithCode('Message from person A');
        [, $codeB] = $this->ticketWithCode('Message from person B');

        $this->post(route('check.store'), ['code' => $codeB]);

        $this->get(route('conversation.show'))->assertSee('Message from person B')->assertDontSee('Message from person A');
        // Ticket ids are never accepted from the client.
        $this->get('/conversation?ticket='.$ticketA->id)->assertDontSee('Message from person A');
    }

    public function test_conversation_routes_require_access(): void
    {
        $this->get(route('conversation.show'))->assertRedirect(route('check.show'));
        $this->get(route('help.show'))->assertRedirect(route('check.show'));
        $this->post(route('conversation.messages'), ['message' => 'hi'])->assertRedirect(route('check.show'));
        $this->postJson(route('conversation.messages'), ['message' => 'hi'])->assertStatus(401);
    }

    public function test_remember_device_is_opt_in_and_can_be_forgotten(): void
    {
        $this->fakeAi();
        $this->startConversation();
        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1']);

        $this->post(route('code.saved'))->assertCookieMissing(config('aisa.tickets.remember_cookie'));

        $this->fakeAi();
        $this->startConversation();
        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1']);
        $response = $this->post(route('code.saved'), ['remember' => '1']);
        $response->assertCookie(config('aisa.tickets.remember_cookie'));

        $this->post(route('forget-device'))->assertCookieExpired(config('aisa.tickets.remember_cookie'));
    }

    public function test_remembered_code_opens_the_conversation(): void
    {
        [$ticket, $code] = $this->ticketWithCode('Remembered conversation');

        $this->withCookie(config('aisa.tickets.remember_cookie'), $code)
            ->post(route('check.remembered'))
            ->assertRedirect(route('conversation.show'));
        $this->get(route('conversation.show'))->assertSee('Remembered conversation');
    }

    public function test_human_reply_is_visible_on_return(): void
    {
        [$ticket, $code] = $this->ticketWithCode();
        $ticket->messages()->create(['sender' => 'human', 'body' => 'Hello, this is Aisa replying personally.', 'approved_at' => now()]);
        $ticket->notes()->create(['body' => 'PRIVATE NOTE never for the user']);

        $this->post(route('check.store'), ['code' => $code]);
        $this->get(route('conversation.show'))
            ->assertSee('Hello, this is Aisa replying personally.')
            ->assertSee('Aisa · personal reply')
            ->assertDontSee('PRIVATE NOTE');
    }
}
