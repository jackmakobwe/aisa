<?php

namespace App\Services\Ai;

/**
 * A language-model backend. Implementations are only ever called from the
 * server; keys never reach the browser.
 */
interface AiProvider
{
    /** Short identifier stored with each analysis, e.g. "anthropic". */
    public function name(): string;

    /** True for the development mock, whose output must be labelled as such. */
    public function isMock(): bool;

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     * @param  array<string, mixed>  $schema  JSON schema the reply must satisfy
     *
     * @throws AiUnavailableException
     */
    public function generateJson(string $system, array $messages, array $schema, int $maxTokens): AiResult;
}
