<?php

namespace Tests;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiProvider;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeAiProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** Swap in a scriptable AI provider and return it. */
    protected function fakeAi(): FakeAiProvider
    {
        $fake = new FakeAiProvider;
        $this->app->instance(AiProvider::class, $fake);

        return $fake;
    }

    /** Sign in as staff with two-factor already passed for this session. */
    protected function actingAsStaff(?User $user = null): User
    {
        $user ??= User::factory()->admin()->withTwoFactor()->create();
        $this->actingAs($user)->withSession(['admin.2fa_passed' => true, 'admin.last_activity' => now()->timestamp]);

        return $user;
    }

    /** Go through the public flow: message, consent, conversation. */
    protected function startConversation(string $message = 'I feel stressed about work and my family.', string $age = 'adult'): Ticket
    {
        $this->post(route('start'), ['message' => $message])->assertRedirect(route('consent.show'));
        $this->post(route('consent.store'), ['age_group' => $age, 'agree' => '1'])->assertRedirect(route('conversation.show'));

        return Ticket::latest('id')->firstOrFail();
    }

    protected function startConversationDirectly(string $message = 'Hello, I need some advice.'): Ticket
    {
        [$ticket] = app(ConversationService::class)->start('en', 'adult', $message);

        return $ticket;
    }
}
