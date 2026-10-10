<?php

namespace App\Services\Safety;

/**
 * Result of assessing one message for risk.
 */
final class SafetyAssessment
{
    public const TYPES = ['self_harm', 'suicide', 'abuse', 'violence', 'immediate_danger', 'child_safeguarding'];

    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public readonly bool $flagged,
        public readonly ?string $level,
        public readonly array $types,
        public readonly string $source,
    ) {}

    public static function none(string $source = 'rules'): self
    {
        return new self(false, null, [], $source);
    }

    /**
     * @param  list<string>  $types
     */
    public static function fromTypes(array $types, string $source): self
    {
        $types = array_values(array_unique(array_intersect($types, self::TYPES)));
        if ($types === []) {
            return self::none($source);
        }

        return new self(true, self::levelFor($types), $types, $source);
    }

    /**
     * Combine two assessments conservatively: anything either one flags is
     * flagged, at the higher of the two levels.
     */
    public function merge(self $other): self
    {
        if (! $this->flagged && ! $other->flagged) {
            return self::none($this->source);
        }

        $source = match (true) {
            $this->flagged && $other->flagged => 'both',
            $this->flagged => $this->source,
            default => $other->source,
        };

        return self::fromTypes(array_merge($this->types, $other->types), $source);
    }

    /**
     * @param  list<string>  $types
     */
    public static function levelFor(array $types): string
    {
        if (array_intersect($types, ['suicide', 'self_harm', 'immediate_danger'])) {
            return 'critical';
        }

        return 'high';
    }
}
