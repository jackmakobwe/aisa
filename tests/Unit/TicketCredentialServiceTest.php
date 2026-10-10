<?php

namespace Tests\Unit;

use App\Services\TicketCredentialService;
use Tests\TestCase;

class TicketCredentialServiceTest extends TestCase
{
    public function test_codes_have_at_least_128_bits_of_randomness_and_a_readable_format(): void
    {
        $service = new TicketCredentialService;
        $code = $service->generate();

        $this->assertMatchesRegularExpression('/^([0-9A-HJKMNP-TV-Z]{4}-){6}[0-9A-HJKMNP-TV-Z]{4}$/', $code);
        // 28 symbols from a 32-symbol alphabet = 140 bits.
        $this->assertGreaterThanOrEqual(128, TicketCredentialService::LENGTH * log(strlen(TicketCredentialService::ALPHABET), 2));
    }

    public function test_codes_do_not_repeat(): void
    {
        $service = new TicketCredentialService;
        $codes = array_map(fn () => $service->generate(), range(1, 2000));

        $this->assertCount(2000, array_unique($codes));
    }

    public function test_normalisation_accepts_case_spacing_and_lookalike_characters(): void
    {
        $service = new TicketCredentialService;
        $code = '0123-4567-89AB-CDEF-GHJK-MNPQ-RSTV';

        $this->assertSame('0123456789ABCDEFGHJKMNPQRSTV', $service->normalize(' o123 4567 89ab-cdef ghjk-mnpq rstv '));
        $this->assertSame($service->normalize($code), $service->normalize(strtolower(str_replace('-', '', $code))));
        $this->assertNull($service->normalize('AISA4821'));
        $this->assertNull($service->normalize(str_repeat('U', 28)));
    }

    public function test_hash_is_keyed_and_never_equals_the_code(): void
    {
        $service = new TicketCredentialService;
        $normalized = $service->normalize($service->generate());
        $hash = $service->hash($normalized);

        $this->assertSame(64, strlen($hash));
        $this->assertStringNotContainsString($normalized, $hash);
        $this->assertNotSame(hash('sha256', $normalized), $hash, 'The hash must be keyed with the pepper.');

        config(['aisa.tickets.pepper' => 'another-pepper']);
        $this->assertNotSame($hash, $service->hash($normalized));
    }
}
