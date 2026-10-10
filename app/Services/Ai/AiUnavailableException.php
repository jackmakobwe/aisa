<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * The AI layer could not produce a usable response. The reason is a short
 * machine code (e.g. "timeout", "rate_limited", "refused") that is safe to
 * store and log: it never contains user text.
 */
class AiUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct('AI unavailable: '.$reason, 0, $previous);
    }
}
