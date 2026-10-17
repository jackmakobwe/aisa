<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Log;

/**
 * Claude via the official Anthropic PHP SDK, using structured outputs so
 * every reply is schema-valid JSON.
 */
class AnthropicProvider implements AiProvider
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $effort,
        private readonly float $timeout,
        private readonly int $maxRetries,
        private readonly bool $refusalFallback,
    ) {}

    public function name(): string
    {
        return 'anthropic';
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

        $params = [
            'model' => $this->model,
            'maxTokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
            'outputConfig' => [
                'effort' => $this->effort,
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
            'requestOptions' => ['timeout' => $this->timeout, 'maxRetries' => $this->maxRetries],
        ];

        if ($this->refusalFallback) {
            // If the model declines on policy grounds, let the API retry on
            // its default fallback model instead of returning nothing.
            $params['fallbacks'] = 'default';
            $params['betas'] = ['server-side-fallback-2026-07-01'];
        }

        try {
            $response = $this->client()->beta->messages->create(...$params);
        } catch (APITimeoutException $e) {
            throw $this->fail('timeout', $e);
        } catch (APIConnectionException $e) {
            throw $this->fail('connection', $e);
        } catch (RateLimitException $e) {
            throw $this->fail('rate_limited', $e);
        } catch (AuthenticationException $e) {
            throw $this->fail('auth', $e);
        } catch (BadRequestException $e) {
            throw $this->fail('bad_request', $e);
        } catch (APIStatusException $e) {
            throw $this->fail('api_'.$e->status, $e);
        }

        if ($response->stopReason === 'refusal') {
            throw new AiUnavailableException('refused');
        }
        if ($response->stopReason === 'max_tokens') {
            throw new AiUnavailableException('truncated');
        }

        $text = null;
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        $data = $text !== null ? json_decode($text, true) : null;
        if (! is_array($data)) {
            throw new AiUnavailableException('invalid_json');
        }

        return new AiResult(
            data: $data,
            model: $response->model,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
        );
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }

    private function fail(string $reason, \Throwable $e): AiUnavailableException
    {
        // Log the class and status only: SDK messages can echo request content.
        Log::warning('AI provider error', ['reason' => $reason, 'exception' => $e::class]);

        return new AiUnavailableException($reason, $e);
    }
}
