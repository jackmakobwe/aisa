<?php

namespace App\Services\Ai;

final class KnowledgeNote
{
    /**
     * @param  list<string>  $keywords  normalised (lowercase ASCII) match terms
     */
    public function __construct(
        public readonly string $id,
        public readonly string $module,
        public readonly string $title,
        public readonly array $keywords,
        public readonly string $body,
        public readonly bool $reviewed,
    ) {}
}
