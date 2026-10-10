<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class SafetyEvaluationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_when_the_model_catches_what_the_rules_miss_and_saves_nothing(): void
    {
        Storage::fake('local');
        $this->fakeAi()->willReturn(FakeAiProvider::turn(['risk' => ['flag' => true, 'types' => ['suicide']]]));

        $this->artisan('aisa:safety-eval', ['--only' => 'sw-06'])->assertSuccessful();

        $this->assertSame(0, Ticket::count(), 'Evaluation must not leave conversations behind.');
        $this->assertCount(1, Storage::disk('local')->files('safety-eval'));
    }

    public function test_fails_on_a_miss_or_a_model_error(): void
    {
        Storage::fake('local');
        $this->fakeAi()->willReturn(FakeAiProvider::turn());
        $this->artisan('aisa:safety-eval', ['--only' => 'sw-06'])->assertFailed();

        $this->fakeAi()->willFail('timeout');
        $this->artisan('aisa:safety-eval', ['--only' => 'en-12'])->assertFailed();
    }
}
