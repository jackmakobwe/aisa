<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A free, open model running locally through Ollama (https://ollama.com).
 * Nothing leaves the machine. Ollama constrains the output to the JSON
 * schema, so replies have the same shape as from the hosted provider.
 *
 * Small local models are weaker at Kiswahili and at noticing risk than
 * hosted ones; the rule-based safety layer still runs first either way,
 * and the launch check keeps requiring the hosted provider.
 */
class OllamaProvider implements AiProvider
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly float $timeout,
        private readonly int $context,
        private readonly string $keepAlive,
    ) {}

    public function name(): string
    {
        return 'ollama';
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
        // Local models on ordinary computers can take minutes; do not let
        // PHP's own time limit cut a web request off first. Only ever raise
        // an existing limit: 0 (the CLI default) means unlimited, and setting
        // a limit there would cap the whole remaining process.
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0 && $limit < $this->timeout + 30) {
            set_time_limit((int) $this->timeout + 30);
        }
        $started = hrtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->url, '/').'/api/chat', [
                    'model' => $this->model,
                    'messages' => [['role' => 'system', 'content' => $system], ...$messages],
                    'format' => $schema,
                    'stream' => false,
                    'keep_alive' => $this->keepAlive,
                    'options' => [
                        'num_ctx' => $this->context,
                        'num_predict' => $maxTokens,
                        'temperature' => 0.4,
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Covers both "Ollama is not running" and timeouts.
            $reason = str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'connection';
            Log::warning('AI provider error', ['reason' => $reason, 'provider' => 'ollama']);

            throw new AiUnavailableException($reason, $e);
        }

        if ($response->status() === 404) {
            // Ollama answers 404 when the model has not been pulled yet.
            throw new AiUnavailableException('model_missing');
        }
        if ($response->failed()) {
            Log::warning('AI provider error', ['reason' => 'api_'.$response->status(), 'provider' => 'ollama']);

            throw new AiUnavailableException('api_'.$response->status());
        }

        if ($response->json('done_reason') === 'length') {
            throw new AiUnavailableException('truncated');
        }

        $data = json_decode((string) $response->json('message.content'), true);
        if (! is_array($data)) {
            throw new AiUnavailableException('invalid_json');
        }

        return new AiResult(
            data: $data,
            model: (string) ($response->json('model') ?? $this->model),
            inputTokens: $response->json('prompt_eval_count'),
            outputTokens: $response->json('eval_count'),
            latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
        );
    }
}
