<?php

namespace App\Services\Ai;

final class DraftOutcome
{
    /**
     * @param  list<string>  $knowledgeIds  reference notes given to the model
     */
    public function __construct(
        public readonly string $draft,
        public readonly bool $sensitive,
        public readonly string $notes,
        public readonly AiResult $result,
        public readonly array $knowledgeIds = [],
    ) {}
}
