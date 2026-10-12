<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class ChatLauncherTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_visitors_get_a_button_that_opens_a_start_form(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSee('id="aisa-chat-panel"', false)
            ->assertSee('Chat with Msaidizi')
            ->assertSee('I am Msaidizi, the AI assistant on Aisa', false)
            ->assertSee('action="'.route('start').'"', false)
            ->assertSee('data-module="money"', false)
            // Without JavaScript the button is a link to the start form.
            ->assertSee('href="'.route('home').'#start"', false);

        $this->withSession(['locale' => 'sw'])->get(route('about'))
            ->assertSee('Ongea na Msaidizi')
            ->assertSee('msaidizi wa AI hapa Aisa');
    }

    public function test_starting_from_the_button_still_requires_consent(): void
    {
        $ai = $this->fakeAi();

        $this->post(route('start'), ['message' => 'How can I start a small business with TSh 100,000?', 'module' => 'money'])
            ->assertRedirect(route('consent.show'));

        $this->assertDatabaseCount('tickets', 0);
        $this->assertSame([], $ai->calls);
    }

    public function test_an_open_conversation_continues_in_the_panel(): void
    {
        $this->fakeAi()->willReturn(FakeAiProvider::turn(['reply' => 'Let us look at your budget together.']));
        $this->startConversation('I feel stuck financially.');

        $this->get(route('articles.index'))->assertOk()
            ->assertSee('I feel stuck financially.')
            ->assertSee('Let us look at your budget together.')
            ->assertSee('action="'.route('conversation.messages').'"', false)
            ->assertSee('Open full conversation')
            ->assertDontSee('action="'.route('start').'"', false);
    }

    public function test_button_is_hidden_where_the_conversation_already_is(): void
    {
        $this->fakeAi();
        $this->post(route('start'), ['message' => 'Hello there']);
        $this->get(route('consent.show'))->assertOk()->assertDontSee('id="aisa-chat-panel"', false);

        $this->post(route('consent.store'), ['age_group' => 'adult', 'agree' => '1']);
        $this->get(route('conversation.show'))->assertOk()->assertDontSee('id="aisa-chat-panel"', false);
        $this->get(route('get-help'))->assertOk()->assertDontSee('id="aisa-chat-panel"', false);
    }

    public function test_button_is_hidden_when_intake_is_closed_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->get(route('about'))->assertOk()->assertDontSee('id="aisa-chat-panel"', false);
    }

    public function test_prompt_teaches_core_qualities_and_reply_style(): void
    {
        $ai = $this->fakeAi();
        $this->startConversation('Mambo');
        $system = $ai->calls[0]['system'];

        $this->assertStringContainsString('## Core qualities', $system);
        $this->assertStringContainsString('## Everyday situations', $system);
        $this->assertStringContainsString('## Examples of the style we want', $system);
        $this->assertStringContainsString('Mimi ni Msaidizi, msaidizi wa AI hapa Aisa', $system);
        $this->assertStringContainsString('Jumla: 300,000.', $system);
    }
}
