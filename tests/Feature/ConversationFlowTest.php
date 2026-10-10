<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\ConsentRecord;
use App\Models\DeletionRequest;
use App\Models\Message;
use App\Models\SafetyFlag;
use App\Models\Ticket;
use App\Services\Ai\AiProvider;
use App\Services\SettingsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class ConversationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_stored_or_sent_to_the_ai_before_consent(): void
    {
        $ai = $this->fakeAi();

        $this->post(route('start'), ['message' => 'Something private'])->assertRedirect(route('consent.show'));
        $this->get(route('consent.show'))->assertOk()->assertSee('Before we start');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertSame([], $ai->calls);
    }

    public function test_declining_consent_discards_the_message(): void
    {
        $this->post(route('start'), ['message' => 'Something private']);
        $this->post(route('consent.decline'))->assertRedirect(route('home'));

        $this->get(route('consent.show'))->assertRedirect(route('home'));
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_consent_and_age_are_required(): void
    {
        $this->post(route('start'), ['message' => 'Something private']);
        $this->post(route('consent.store'), [])->assertSessionHasErrors(['agree', 'age_group']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_message_validation(): void
    {
        $this->post(route('start'), ['message' => ''])->assertSessionHasErrors('message');
        $this->post(route('start'), ['message' => str_repeat('a', config('aisa.limits.message_max_chars') + 1)])->assertSessionHasErrors('message');
    }

    public function test_full_flow_creates_ticket_consent_messages_and_analysis(): void
    {
        $ai = $this->fakeAi();
        $ticket = $this->startConversation('My manager keeps shouting at me at work.');

        $this->assertDatabaseHas('consent_records', ['consent_version' => config('aisa.consent_version'), 'age_group' => 'adult', 'accepted' => true]);
        $this->assertSame(ConsentRecord::first()->id, $ticket->consent_record_id);
        $this->assertSame(['user', 'ai'], $ticket->messages->pluck('sender')->all());
        $this->assertSame('work', $ticket->fresh()->category);
        $this->assertDatabaseHas('ai_analyses', ['ticket_id' => $ticket->id, 'kind' => 'turn', 'provider' => 'fake', 'prompt_version' => config('aisa.ai.prompt_version')]);
        $this->assertNull($ticket->code_hash, 'No code is issued until the person chooses.');
        $this->assertCount(1, $ai->calls);

        $this->get(route('conversation.show'))->assertOk()
            ->assertSee('My manager keeps shouting at me at work.')
            ->assertSee('AI assistant');
    }

    public function test_message_text_is_encrypted_at_rest(): void
    {
        $this->fakeAi();
        $this->startConversation('A very private sentence about my family.');

        $raw = DB::table('messages')->where('sender', 'user')->value('body');
        $this->assertStringNotContainsString('private sentence', $raw);
        $this->assertSame('A very private sentence about my family.', Message::where('sender', 'user')->first()->body);
    }

    public function test_follow_up_via_json_returns_rendered_messages(): void
    {
        $this->fakeAi();
        $this->startConversation();

        $this->postJson(route('conversation.messages'), ['message' => 'Thank you, that helps a bit.'])
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonPath('safety', false)
            ->assertJsonStructure(['html', 'error', 'can_retry', 'suggest_human', 'safety']);

        $this->assertSame(4, Message::count());
    }

    public function test_history_is_sent_to_the_model_in_chronological_order_without_identifiers(): void
    {
        $ai = $this->fakeAi();
        $this->post(route('start'), ['message' => 'First message']);
        $this->post(route('consent.store'), ['age_group' => 'adult', 'agree' => '1', 'display_name' => 'Zawadi']);
        $this->postJson(route('conversation.messages'), ['message' => 'Second message']);

        $sent = end($ai->calls)['messages'];
        $this->assertSame('First message', $sent[0]['content']);
        $this->assertSame('user', $sent[0]['role']);
        $this->assertSame('Second message', end($sent)['content']);
        $this->assertStringNotContainsString('Zawadi', json_encode($ai->calls));
    }

    public function test_ai_failure_keeps_the_message_and_allows_retry(): void
    {
        $ai = $this->fakeAi()->willFail('timeout');
        $ticket = $this->startConversation('Hello, are you there?');

        $this->assertSame(['user'], $ticket->messages()->pluck('sender')->all());
        $this->assertDatabaseHas('ai_analyses', ['ticket_id' => $ticket->id, 'error' => 'timeout']);
        $this->get(route('conversation.show'))->assertSee('could not reply just now');

        $this->postJson(route('conversation.retry'))->assertOk()->assertJsonPath('error', null);
        $this->assertSame(['user', 'ai'], $ticket->messages()->pluck('sender')->all());
    }

    public function test_json_error_when_ai_unavailable(): void
    {
        $this->fakeAi()->willReturn(FakeAiProvider::turn())->willFail('rate_limited');
        $this->startConversation();

        $this->postJson(route('conversation.messages'), ['message' => 'Another message'])
            ->assertOk()
            ->assertJsonPath('can_retry', true)
            ->assertJsonPath('error', __('chat.ai_unavailable'));
    }

    public function test_daily_ai_cap_stops_calls_but_keeps_messages(): void
    {
        config(['aisa.ai.daily_request_cap' => 1]);
        $ai = $this->fakeAi();
        $this->startConversation();
        $this->postJson(route('conversation.messages'), ['message' => 'Second message'])->assertJsonPath('can_retry', true);

        $this->assertCount(1, $ai->calls);
        $this->assertDatabaseHas('ai_analyses', ['error' => 'daily_cap']);
    }

    public function test_invalid_model_output_is_sanitised(): void
    {
        $this->fakeAi()->willReturn(FakeAiProvider::turn(['category' => 'astrology', 'urgency' => 'extreme', 'language' => 'fr']));
        $ticket = $this->startConversation();

        $analysis = AiAnalysis::first();
        $this->assertSame('other', $analysis->category);
        $this->assertSame('medium', $analysis->urgency);
        $this->assertNull($analysis->detected_language);
        $this->assertSame('other', $ticket->fresh()->category);
    }

    public function test_choosing_talk_to_aisa_requires_agreement_issues_a_code_and_prepares_a_draft(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();

        $this->post(route('help.store'), ['mode' => 'human'])->assertSessionHasErrors('share_ok');

        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1'])->assertRedirect(route('code.issued'));
        $ticket->refresh();
        $this->assertSame('human', $ticket->help_mode);
        $this->assertSame(Ticket::STATUS_AWAITING_HUMAN, $ticket->status);
        $this->assertNotNull($ticket->code_hash);
        $this->assertDatabaseHas('ai_analyses', ['ticket_id' => $ticket->id, 'kind' => 'draft']);

        $this->get(route('code.issued'))->assertOk()->assertSee('Save your private code');
        $this->post(route('code.saved'));
        $this->get(route('code.issued'))->assertRedirect(route('conversation.show'));
    }

    public function test_ai_stops_replying_once_aisa_is_requested(): void
    {
        $ai = $this->fakeAi();
        $this->startConversation();
        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1']);
        $calls = count($ai->calls);

        $this->postJson(route('conversation.messages'), ['message' => 'Here is more context for Aisa.'])->assertOk();

        $this->assertCount($calls, $ai->calls);
        $this->assertSame('user', Message::latest('id')->first()->sender);
    }

    public function test_human_requests_can_be_paused_for_capacity(): void
    {
        $this->fakeAi();
        app(SettingsService::class)->set('human_requests_open', false);
        $ticket = $this->startConversation();

        $this->get(route('help.show'))->assertSee('not taking new personal requests');
        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1'])->assertSessionHas('status');
        $this->assertNull($ticket->fresh()->help_mode);
    }

    public function test_ai_only_choice_and_optional_code(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();

        $this->post(route('help.store'), ['mode' => 'ai'])->assertRedirect(route('conversation.show'));
        $this->assertSame('ai', $ticket->fresh()->help_mode);
        $this->assertNull($ticket->fresh()->code_hash);

        $this->post(route('code.issue'))->assertRedirect(route('code.issued'));
        $this->assertNotNull($ticket->fresh()->code_hash);

        $this->post(route('code.issue'))->assertRedirect(route('conversation.show'))->assertSessionHas('status', __('ticket.already_issued'));
    }

    public function test_under_18_is_flagged_for_review_under_restrict_policy(): void
    {
        $ai = $this->fakeAi();
        $ticket = $this->startConversation('I am having problems at school', 'minor');

        $this->assertTrue($ticket->is_minor);
        $this->assertDatabaseHas('safety_flags', ['ticket_id' => $ticket->id, 'level' => 'concern', 'source' => 'minor']);
        $this->assertStringContainsString('under 18', $ai->calls[0]['system']);
    }

    public function test_under_18_is_redirected_under_block_policy(): void
    {
        config(['aisa.minor_policy' => 'block']);
        $this->post(route('start'), ['message' => 'Hello']);

        $this->post(route('consent.store'), ['age_group' => 'minor', 'agree' => '1'])->assertRedirect(route('under-18'));
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_deletion_request_blocks_new_messages(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();

        $this->post(route('conversation.delete'))->assertSessionHasErrors('confirm_delete');
        $this->post(route('conversation.delete'), ['confirm_delete' => '1'])->assertRedirect(route('conversation.show'));

        $this->assertDatabaseHas('deletion_requests', ['ticket_id' => $ticket->id, 'status' => DeletionRequest::STATUS_PENDING]);
        $this->postJson(route('conversation.messages'), ['message' => 'one more'])->assertStatus(409);
    }

    public function test_user_message_html_is_escaped(): void
    {
        $this->fakeAi();
        $this->startConversation('<script>alert("x")</script> hello');

        $this->get(route('conversation.show'))
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_message_endpoint_is_rate_limited(): void
    {
        $this->fakeAi();
        $this->startConversation();

        foreach (range(1, config('aisa.limits.messages_per_minute')) as $i) {
            $this->postJson(route('conversation.messages'), ['message' => "message {$i}"]);
        }
        $this->postJson(route('conversation.messages'), ['message' => 'too many'])->assertStatus(429);
    }

    public function test_leaving_revokes_access(): void
    {
        $this->fakeAi();
        $this->startConversation();

        $this->post(route('conversation.leave'))->assertRedirect(route('home'));
        $this->get(route('conversation.show'))->assertRedirect(route('check.show'));
    }

    public function test_quick_exit_ends_the_session_and_forgets_the_device(): void
    {
        $this->fakeAi();
        $this->startConversation();

        $this->post(route('quick-exit'))->assertNoContent()->assertCookieExpired(config('aisa.tickets.remember_cookie'));
        $this->get(route('conversation.show'))->assertRedirect(route('check.show'));
        $this->postJson(route('conversation.messages'), ['message' => 'after exit'])->assertStatus(401);
    }

    public function test_rating_only_after_a_human_reply(): void
    {
        $this->fakeAi();
        $ticket = $this->startConversation();

        $this->post(route('conversation.rating'), ['rating' => 5]);
        $this->assertNull($ticket->fresh()->rating);

        $ticket->messages()->create(['sender' => 'human', 'body' => 'Hi, this is Aisa.', 'approved_at' => now()]);
        $this->post(route('conversation.rating'), ['rating' => 4]);
        $this->assertSame(4, $ticket->fresh()->rating);
    }

    public function test_launch_gate_closes_intake_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        // CSRF is enforced outside the testing environment; not what this test is about.
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('start'), ['message' => 'Hello'])->assertRedirect(route('not-open'));
        $this->get(route('not-open'))->assertOk()->assertSee('Aisa is not open yet');
    }

    public function test_mock_provider_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->app->forgetInstance(AiProvider::class);

        $this->expectException(\RuntimeException::class);
        $this->app->make(AiProvider::class);
    }

    public function test_mock_replies_are_labelled_as_simulated(): void
    {
        $this->startConversation('Nina stress kuhusu kazi');

        $this->assertTrue(Message::where('sender', 'ai')->first()->is_mock);
        $this->get(route('conversation.show'))->assertSee('Simulated (development)');
    }

    public function test_safety_flags_are_never_resolved_by_further_messages(): void
    {
        $this->fakeAi();
        $this->startConversation('I want to end my life');
        $this->postJson(route('conversation.messages'), ['message' => 'Actually I feel fine now, all good']);

        $this->assertSame(1, SafetyFlag::count());
        $this->assertSame(SafetyFlag::STATUS_OPEN, SafetyFlag::first()->status);
    }
}
