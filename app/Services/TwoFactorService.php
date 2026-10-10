<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Time-based one-time passwords (RFC 6238) for dashboard staff, with
 * single-use recovery codes stored as hashes.
 */
class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    /**
     * Verify a 6-digit code. Each accepted code can be used only once,
     * which stops replay within the validity window.
     */
    public function verify(string $secret, string $code, int $userId): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp = $this->google2fa->verifyKeyNewer($secret, $code, Cache::get("2fa.last.{$userId}"), 1);
        if ($timestamp === false) {
            return false;
        }

        Cache::put("2fa.last.{$userId}", $timestamp === true ? $this->google2fa->getTimestamp() : $timestamp, now()->addMinutes(5));

        return true;
    }

    /** @return array{0: list<string>, 1: list<string>} plaintext codes and their hashes */
    public function newRecoveryCodes(): array
    {
        $plain = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();

        return [$plain, array_map(fn ($c) => Hash::make($c), $plain)];
    }

    /** Consume a recovery code. Returns true if it matched an unused code. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $i => $hash) {
            if (Hash::check(Str::upper(trim($code)), $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
