<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Any hosted model behind an OpenAI-compatible chat completions API, such
 * as Groq or Google Gemini. Used for development and testing on their free
 * tiers; the launch check still requires the reviewed hosted provider.
 *
 * JSON mode "schema" sends the JSON schema so the API enforces it; "object"
 * only asks for a JSON object and adds the schema to the instructions, for
 * models without schema support. ConversationAi validates every field
 * either way.
 */
class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $jsonMode,
        private readonly float $timeout,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isMock(): bool
    {
        return false;
    }

    public function generateJson(string $system, array $messages, array $schema, int $maxTokens): AiResult
    {
        $started = hrtime(true);

        if ($this->jsonMode === 'schema') {
            $format = ['type' => 'json_schema', 'json_schema' => ['name' => 'reply', 'strict' => true, 'schema' => $schema]];
        } else {
            $format = ['type' => 'json_object'];
            $system .= "\n\nReturn only a JSON object that matches this JSON schema exactly:\n".json_encode($schema, JSON_UNESCAPED_SLASHES);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(rtrim($this->url, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => [['role' => 'system', 'content' => $system], ...$messages],
                    'response_format' => $format,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.4,
                ]);
        } catch (ConnectionException $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'connection';

            throw $this->fail($reason, $e);
        }

        if ($response->failed()) {
            throw $this->fail(match ($response->status()) {
                401, 403 => 'auth',
                404 => 'model_missing',
                429 => 'rate_limited',
                400 => 'bad_request',
                default => 'api_'.$response->status(),
            });
        }

        $finish = $response->json('choices.0.finish_reason');
        if ($finish === 'length') {
            throw new AiUnavailableException('truncated');
        }
        if ($finish === 'content_filter') {
            throw new AiUnavailableException('refused');
        }

        $data = json_decode($this->stripFences((string) $response->json('choices.0.message.content')), true);
        if (! is_array($data)) {
            throw new AiUnavailableException('invalid_json');
        }

        return new AiResult(
            data: $data,
            model: (string) ($response->json('model') ?? $this->model),
            inputTokens: $response->json('usage.prompt_tokens'),
            outputTokens: $response->json('usage.completion_tokens'),
            latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
        );
    }

    /** Some models wrap JSON in a ```json code fence even in JSON mode. */
    private function stripFences(string $text): string
    {
        $text = trim($text);

        return preg_match('/\A```(?:json)?\s*(.*?)\s*```\z/s', $text, $m) ? $m[1] : $text;
    }

    private function fail(string $reason, ?\Throwable $e = null): AiUnavailableException
    {
        // Log the reason only: API error bodies can echo request content.
        Log::warning('AI provider error', ['reason' => $reason, 'provider' => $this->name]);

        return new AiUnavailableException($reason, $e);
    }
}
