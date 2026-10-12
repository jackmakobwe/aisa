<?php

namespace App\Services\Ai;

use App\Services\Safety\SafetyAssessment;

final class TurnOutcome
{
    /**
     * @param  list<string>  $knowledgeIds  reference notes given to the model
     */
    public function __construct(
        public readonly string $reply,
        public readonly ?string $language,
        public readonly string $category,
        public readonly string $urgency,
        public readonly SafetyAssessment $risk,
        public readonly string $summary,
        public readonly bool $suggestHuman,
        public readonly AiResult $result,
        public readonly bool $isMock,
        public readonly array $knowledgeIds = [],
    ) {}
}
