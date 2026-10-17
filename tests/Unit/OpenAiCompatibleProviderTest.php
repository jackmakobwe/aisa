<?php

namespace Tests\Unit;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\OpenAiCompatibleProvider;
use App\Services\Ai\Prompts;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\FakeAiProvider;
use Tests\TestCase;

class OpenAiCompatibleProviderTest extends TestCase
{
    private function provider(string $json = 'schema'): OpenAiCompatibleProvider
    {
        return new OpenAiCompatibleProvider('gemini', 'https://api.example.test/v1', 'test-key', 'test-model', $json, 30);
    }

    private function completion(string $content, string $finish = 'stop'): array
    {
        return [
            'model' => 'test-model-001',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finish]],
            'usage' => ['prompt_tokens' => 4200, 'completion_tokens' => 180],
        ];
    }

    public function test_schema_mode_sends_the_schema_and_parses_the_reply(): void
    {
        Http::fake(['api.example.test/*' => Http::response($this->completion(json_encode(FakeAiProvider::turn(['reply' => 'Habari! Mimi ni msaidizi wa AI.']))))]);

        $result = $this->provider()->generateJson('SYSTEM', [['role' => 'user', 'content' => 'Mambo']], Prompts::turnSchema(), 2000);

        $this->assertSame('Habari! Mimi ni msaidizi wa AI.', $result->data['reply']);
        $this->assertSame('test-model-001', $result->model);
        $this->assertSame(4200, $result->inputTokens);
        $this->assertSame(180, $result->outputTokens);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.example.test/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer test-key')
            && $r['model'] === 'test-model'
            && $r['messages'][0] === ['role' => 'system', 'content' => 'SYSTEM']
            && $r['messages'][1] === ['role' => 'user', 'content' => 'Mambo']
            && $r['response_format']['type'] === 'json_schema'
            && $r['response_format']['json_schema']['schema'] === Prompts::turnSchema()
            && $r['max_tokens'] === 2000);
    }

    public function test_object_mode_puts_the_schema_in_the_instructions_and_strips_code_fences(): void
    {
        Http::fake(['*' => Http::response($this->completion("```json\n{\"reply\": \"Hi, I am an AI assistant.\"}\n```"))]);

        $result = $this->provider('object')->generateJson('SYSTEM', [['role' => 'user', 'content' => 'Hi']], Prompts::turnSchema(), 100);

        $this->assertSame('Hi, I am an AI assistant.', $result->data['reply']);
        Http::assertSent(fn (Request $r) => $r['response_format'] === ['type' => 'json_object']
            && str_contains($r['messages'][0]['content'], 'matches this JSON schema exactly')
            && str_contains($r['messages'][0]['content'], '"asked_question"'));
    }

    public function test_maps_failures_to_short_reasons(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => 'bad key'], 401)
            ->push(['error' => 'no such model'], 404)
            ->push(['error' => 'slow down'], 429)
            ->push(['error' => 'invalid'], 400)
            ->push(['error' => 'down'], 503)
            ->push($this->completion('{"reply": "Hab', 'length'))
            ->push($this->completion('', 'content_filter'))
            ->push($this->completion('not json')),
        ]);

        foreach (['auth', 'model_missing', 'rate_limited', 'bad_request', 'api_503', 'truncated', 'refused', 'invalid_json'] as $reason) {
            try {
                $this->provider()->generateJson('S', [['role' => 'user', 'content' => 'x']], [], 10);
                $this->fail("Expected {$reason}");
            } catch (AiUnavailableException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
    }

    public function test_network_failure_is_a_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

        $this->expectExceptionObject(new AiUnavailableException('connection'));
        $this->provider()->generateJson('S', [['role' => 'user', 'content' => 'x']], [], 10);
    }

    public function test_container_builds_groq_and_gemini_and_requires_a_key(): void
    {
        foreach (['groq' => 'GROQ_API_KEY', 'gemini' => 'GEMINI_API_KEY'] as $name => $env) {
            config(['aisa.ai.provider' => $name, "aisa.ai.{$name}.api_key" => 'k']);
            $this->app->forgetInstance(AiProvider::class);
            $provider = $this->app->make(AiProvider::class);

            $this->assertInstanceOf(OpenAiCompatibleProvider::class, $provider);
            $this->assertSame($name, $provider->name());
            $this->assertSame(config("aisa.ai.{$name}.model"), $provider->model());

            config(["aisa.ai.{$name}.api_key" => null]);
            $this->app->forgetInstance(AiProvider::class);
            try {
                $this->app->make(AiProvider::class);
                $this->fail("Expected a missing {$env} error");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($env, $e->getMessage());
            }
        }
    }
}
