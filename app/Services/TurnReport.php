<?php

namespace App\Services;

use App\Models\Message;

/**
 * What happened when a user message was processed, for the response.
 */
final class TurnReport
{
    /**
     * @param  list<Message>  $newMessages  messages to show after the user's own
     */
    public function __construct(
        public readonly Message $userMessage,
        public readonly array $newMessages = [],
        public readonly ?string $aiError = null,
        public readonly bool $safetyRaised = false,
        public readonly bool $suggestHuman = false,
    ) {}
}
