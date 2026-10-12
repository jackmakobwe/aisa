<?php

namespace Tests\Unit;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\OllamaProvider;
use App\Services\Ai\Prompts;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class OllamaProviderTest extends TestCase
{
    private function provider(): OllamaProvider
    {
        return new OllamaProvider('http://127.0.0.1:11434', 'qwen2.5:3b', 60, 8192, '30m');
    }

    public function test_sends_system_prompt_history_and_schema_and_parses_the_reply(): void
    {
        Http::fake(['127.0.0.1:11434/api/chat' => Http::response([
            'model' => 'qwen2.5:3b',
            'message' => ['role' => 'assistant', 'content' => json_encode(FakeAiProvider::turn(['reply' => 'Habari! Mimi ni msaidizi wa AI.']))],
            'done' => true,
            'done_reason' => 'stop',
            'prompt_eval_count' => 3100,
            'eval_count' => 120,
        ])]);

        $result = $this->provider()->generateJson('SYSTEM', [['role' => 'user', 'content' => 'Mambo']], Prompts::turnSchema(), 2000);

        $this->assertSame('Habari! Mimi ni msaidizi wa AI.', $result->data['reply']);
        $this->assertSame('qwen2.5:3b', $result->model);
        $this->assertSame(3100, $result->inputTokens);
        $this->assertSame(120, $result->outputTokens);

        Http::assertSent(function (Request $request) {
            return $request['model'] === 'qwen2.5:3b'
                && $request['stream'] === false
                && $request['messages'][0] === ['role' => 'system', 'content' => 'SYSTEM']
                && $request['messages'][1] === ['role' => 'user', 'content' => 'Mambo']
                && $request['format'] === Prompts::turnSchema()
                && $request['options']['num_ctx'] === 8192
                && $request['options']['num_predict'] === 2000;
        });
    }

    public function test_maps_failures_to_short_reasons(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => 'model not found'], 404)
            ->push(['error' => 'boom'], 500)
            ->push(['message' => ['content' => '{"reply": "Hab'], 'done_reason' => 'length'])
            ->push(['message' => ['content' => 'not json'], 'done_reason' => 'stop']),
        ]);

        foreach (['model_missing', 'api_500', 'truncated', 'invalid_json'] as $reason) {
            try {
                $this->provider()->generateJson('S', [['role' => 'user', 'content' => 'x']], [], 10);
                $this->fail("Expected {$reason}");
            } catch (AiUnavailableException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_ollama_not_running_is_a_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        try {
            $this->provider()->generateJson('S', [['role' => 'user', 'content' => 'x']], [], 10);
            $this->fail('Expected a connection error');
        } catch (AiUnavailableException $e) {
            $this->assertSame('connection', $e->reason);
        }
    }

    public function test_container_builds_the_ollama_provider_from_config(): void
    {
        config(['aisa.ai.provider' => 'ollama']);
        $this->app->forgetInstance(AiProvider::class);

        $provider = $this->app->make(AiProvider::class);

        $this->assertInstanceOf(OllamaProvider::class, $provider);
        $this->assertFalse($provider->isMock());
    }

    public function test_varying_details_come_last_so_the_prompt_prefix_is_stable(): void
    {
        $a = Prompts::turnSystem('Msaidizi', 0, 3, false, 'en', 48);
        $b = Prompts::turnSystem('Msaidizi', 5, 3, true, 'sw', 48, 'money');
        $fixedEnd = strpos($a, '## This conversation');

        $this->assertNotFalse($fixedEnd);
        $this->assertSame(substr($a, 0, $fixedEnd), substr($b, 0, $fixedEnd));
        $this->assertStringContainsString('You have replied 5 time(s)', substr($b, $fixedEnd));
        $this->assertStringContainsString('under 18', substr($b, $fixedEnd));
    }
}
