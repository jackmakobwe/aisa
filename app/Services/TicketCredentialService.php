<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Issues and verifies anonymous ticket codes.
 *
 * A code is 28 symbols from Crockford's base32 alphabet (140 bits of
 * randomness from random_int, a CSPRNG), shown as seven groups of four.
 * Only an HMAC-SHA256 of the normalised code is stored, keyed with a
 * server-side pepper, so a database leak alone does not reveal codes.
 * Because codes are long and random, a fast keyed hash is sufficient;
 * guessing is additionally rate limited at the HTTP layer.
 */
class TicketCredentialService
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const LENGTH = 28;

    public const GROUP = 4;

    public function generate(): string
    {
        $symbols = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $symbols .= self::ALPHABET[random_int(0, 31)];
        }

        return $this->format($symbols);
    }

    public function format(string $normalized): string
    {
        return implode('-', str_split($normalized, self::GROUP));
    }

    /**
     * Normalise user input: uppercase, drop separators and whitespace, and
     * map look-alike characters the way Crockford base32 does. Returns null
     * when the input cannot be a valid code.
     */
    public function normalize(string $input): ?string
    {
        $code = strtoupper(preg_replace('/[\s\-_.]+/u', '', $input) ?? '');
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $code;
    }

    public function hash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, $this->pepper());
    }

    /**
     * Find the ticket for a code. Returns null for malformed, unknown and
     * blocked codes alike, so callers cannot tell them apart.
     */
    public function find(string $input): ?Ticket
    {
        $normalized = $this->normalize($input);
        if ($normalized === null) {
            // Spend comparable time so malformed input is not distinguishable by timing.
            $this->hash(str_repeat('0', self::LENGTH));

            return null;
        }

        $ticket = Ticket::where('code_hash', $this->hash($normalized))->first();

        return $ticket && ! $ticket->isBlocked() ? $ticket : null;
    }

    /**
     * Issue a code for a ticket that does not have one yet and return the
     * plaintext. The plaintext is never stored.
     */
    public function issue(Ticket $ticket): string
    {
        if ($ticket->hasCode()) {
            throw new RuntimeException('This ticket already has a code.');
        }

        $code = $this->generate();
        $ticket->forceFill([
            'code_hash' => $this->hash($this->normalize($code)),
            'code_issued_at' => now(),
        ])->save();

        return $code;
    }

    /** Short, non-secret reference used by staff, e.g. "K7Q-M2D". */
    public function newReference(): string
    {
        do {
            $raw = '';
            for ($i = 0; $i < 6; $i++) {
                $raw .= self::ALPHABET[random_int(0, 31)];
            }
            $reference = substr($raw, 0, 3).'-'.substr($raw, 3);
        } while (Ticket::where('reference', $reference)->exists());

        return $reference;
    }

    private function pepper(): string
    {
        $pepper = (string) config('aisa.tickets.pepper');
        if ($pepper !== '') {
            return $pepper;
        }

        // Development fallback derived from APP_KEY. The launch check
        // reports a missing pepper as a blocker for production.
        return hash('sha256', 'aisa-ticket-pepper|'.Str::after((string) config('app.key'), 'base64:'));
    }
}
