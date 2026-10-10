<?php

namespace App\Services\Ai;

final class AiResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly string $model,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly int $latencyMs = 0,
    ) {}
}
