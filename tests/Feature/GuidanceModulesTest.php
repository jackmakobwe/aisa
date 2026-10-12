<?php

namespace Tests\Feature;

use App\Models\AiAnalysis;
use App\Models\Ticket;
use App\Services\LaunchReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class GuidanceModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_the_four_guidance_areas_in_both_languages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Emotional wellbeing')
            ->assertSee('Parenting &amp; family', false)
            ->assertSee('Money &amp; business', false)
            ->assertSee('Youth &amp; career', false)
            ->assertSee(e(route('home', ['topic' => 'money', 'ask' => 0])), false);

        $this->withSession(['locale' => 'sw'])->get('/')
            ->assertSee('Pesa na biashara')
            ->assertSee('Malezi na familia');
    }

    public function test_topic_link_preselects_the_area_and_fills_an_example(): void
    {
        $example = __('home.modules.money.examples')[0];

        $this->get('/?topic=money&ask=0')->assertOk()
            ->assertSee('value="money" class="sr-only" checked', false)
            ->assertSee(e($example), false);

        // Unknown values are ignored rather than shown.
        $this->get('/?topic=crypto&ask=9')->assertOk()
            ->assertSee('value="" class="sr-only" checked', false);
    }

    public function test_chosen_area_is_stored_and_given_to_the_ai_with_reference_notes(): void
    {
        $ai = $this->fakeAi()->willReturn(FakeAiProvider::turn(['category' => 'money']));

        $this->post(route('start'), ['message' => 'Nina mshahara wa TSh 300,000 kwa mwezi, nigawanyeje matumizi yangu?', 'module' => 'money']);
        $this->post(route('consent.store'), ['age_group' => 'adult', 'agree' => '1'])->assertRedirect(route('conversation.show'));

        $ticket = Ticket::latest('id')->firstOrFail();
        $this->assertSame('money', $ticket->module);
        $this->assertSame('money', $ticket->category);

        $system = $ai->calls[0]['system'];
        $this->assertStringContainsString('started from the "Money:', $system);
        $this->assertStringContainsString('<note id="money-budgeting"', $system);
        $this->assertStringContainsString('awaiting professional review', $system);
        $this->assertStringContainsString('Never guarantee profits', $system);
        // Notes are trusted reference text in the system prompt, never in user turns.
        $this->assertStringNotContainsString('<note', json_encode($ai->calls[0]['messages']));

        $analysis = AiAnalysis::where('ticket_id', $ticket->id)->where('kind', 'turn')->firstOrFail();
        $this->assertContains('money-budgeting', $analysis->knowledge_refs);
    }

    public function test_area_is_optional_and_validated(): void
    {
        $this->post(route('start'), ['message' => 'Hello', 'module' => 'crypto'])->assertSessionHasErrors('module');

        $ai = $this->fakeAi();
        $ticket = $this->startConversation('Habari, nina swali tu.');
        $this->assertNull($ticket->module);
        $this->assertStringNotContainsString('started from the', $ai->calls[0]['system']);
        $this->assertStringNotContainsString('## Reference notes', $ai->calls[0]['system']);
        $this->assertNull(AiAnalysis::where('ticket_id', $ticket->id)->first()->knowledge_refs);
    }

    public function test_follow_up_messages_retrieve_notes_for_the_new_topic(): void
    {
        $ai = $this->fakeAi();
        $this->startConversation('I feel so lonely since I moved to Dodoma.');
        $this->assertStringContainsString('<note id="wellbeing-loneliness"', $ai->calls[0]['system']);

        $this->post(route('conversation.messages'), ['message' => 'Also someone online says I can double my money with forex. Is it a scam?']);
        $this->assertStringContainsString('<note id="money-scams"', $ai->calls[1]['system']);
    }

    public function test_draft_for_aisa_uses_reference_notes(): void
    {
        $ai = $this->fakeAi();
        $ticket = $this->startConversation('My son is 5 and very stubborn. Should I beat him with a stick?');
        $this->post(route('help.store'), ['mode' => 'human', 'share_ok' => '1']);

        $draftCall = collect($ai->calls)->first(fn ($c) => isset($c['schema']['properties']['draft']));
        $this->assertNotNull($draftCall);
        $this->assertStringContainsString('<note id="parenting-positive-discipline"', $draftCall['system']);

        $draft = AiAnalysis::where('ticket_id', $ticket->id)->where('kind', 'draft')->firstOrFail();
        $this->assertContains('parenting-positive-discipline', $draft->knowledge_refs);
    }

    public function test_responders_see_the_area_and_notes_used(): void
    {
        $this->fakeAi();
        $this->post(route('start'), ['message' => 'How can I start a small business with TSh 100,000?', 'module' => 'money']);
        $this->post(route('consent.store'), ['age_group' => 'adult', 'agree' => '1']);
        $ticket = Ticket::latest('id')->firstOrFail();

        $this->actingAsStaff();
        $this->get(route('admin.conversations.show', $ticket))->assertOk()
            ->assertSee('Money &amp; business', false)
            ->assertSee('money-small-business');
        $this->get(route('admin.conversations.index'))->assertOk()->assertSee('via Money');
        $this->get(route('admin.analytics'))->assertOk()->assertSee('Starting area');
    }

    public function test_unreviewed_knowledge_is_a_launch_blocker(): void
    {
        $blocker = fn () => collect(app(LaunchReadiness::class)->checks())->firstWhere('key', 'knowledge.reviewed');

        config(['aisa.knowledge.reviewed' => true]);
        $this->assertFalse($blocker()['ok'], 'The shipped notes are drafts, so the flag alone is not enough');

        config(['aisa.knowledge.reviewed' => false]);
        $this->assertFalse($blocker()['ok']);
    }

    public function test_knowledge_command_lists_and_matches_notes(): void
    {
        $this->artisan('aisa:knowledge')->expectsOutputToContain('still need professional review')->assertSuccessful();
        $this->artisan('aisa:knowledge', ['message' => 'How do I spot a forex scam?'])->expectsOutputToContain('money-scams')->assertSuccessful();
        $this->artisan('aisa:knowledge', ['message' => 'Habari'])->expectsOutputToContain('No reference notes match')->assertSuccessful();
    }
}
