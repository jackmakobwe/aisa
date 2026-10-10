<?php

namespace App\Services\Ai;

final class DraftOutcome
{
    public function __construct(
        public readonly string $draft,
        public readonly bool $sensitive,
        public readonly string $notes,
        public readonly AiResult $result,
    ) {}
}
