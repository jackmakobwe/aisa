<?php

namespace Tests\Support;

use App\Services\Ai\AiProvider;
use App\Services\Ai\AiResult;
use App\Services\Ai\AiUnavailableException;

/**
 * Scriptable provider for tests. Records every call so tests can check
 * what would have been sent to a real model.
 */
class FakeAiProvider implements AiProvider
{
    /** @var list<array{system: string, messages: array, schema: array}> */
    public array $calls = [];

    /** @var list<array|AiUnavailableException> */
    private array $queue = [];

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function isMock(): bool
    {
        return false;
    }

    public function willReturn(array $data): self
    {
        $this->queue[] = $data;

        return $this;
    }

    public function willFail(string $reason = 'timeout'): self
    {
        $this->queue[] = new AiUnavailableException($reason);

        return $this;
    }

    public static function turn(array $overrides = []): array
    {
        return array_replace_recursive([
            'reply' => 'I am an AI assistant. Thank you for sharing that.',
            'language' => 'en',
            'category' => 'work',
            'urgency' => 'low',
            'risk' => ['flag' => false, 'types' => []],
            'summary' => 'Person is stressed about work.',
            'suggest_human' => false,
            'asked_question' => true,
        ], $overrides);
    }

    public function generateJson(string $system, array $messages, array $schema, int $maxTokens): AiResult
    {
        $this->calls[] = compact('system', 'messages', 'schema');
        $next = array_shift($this->queue);

        if ($next instanceof AiUnavailableException) {
            throw $next;
        }

        $data = $next ?? (isset($schema['properties']['draft'])
            ? ['draft' => 'Draft reply for review.', 'sensitive' => false, 'notes_for_responder' => '']
            : self::turn());

        return new AiResult($data, 'fake-model', 10, 20, 5);
    }
}
