<?php

namespace App\Models;

use App\Notifications\MemberResetPassword;
use App\Notifications\MemberVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * An optional account for someone using the public site. Separate from
 * User (staff) and signed in through its own "member" guard.
 *
 * The email is encrypted at rest; email_hash (a keyed hash) is used for
 * lookups and, through getEmailForPasswordReset(), for reset tokens, so the
 * address itself is never stored in plain text.
 */
class Member extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use Notifiable;

    protected $fillable = ['name', 'locale'];

    protected $hidden = ['password', 'email_hash', 'google_id_hash'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'email' => 'encrypted',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function findByEmail(string $email): ?self
    {
        return static::where('email_hash', static::emailHash($email))->first();
    }

    public static function findByGoogleId(string $googleId): ?self
    {
        return static::where('google_id_hash', static::lookupHash('google', $googleId))->first();
    }

    public static function emailHash(string $email): string
    {
        return static::lookupHash('email', Str::lower(trim($email)));
    }

    /** Sets the email and its lookup hash together, so they never disagree. */
    public function setEmail(string $email): static
    {
        $email = Str::lower(trim($email));
        $this->email = $email;
        $this->email_hash = static::emailHash($email);

        return $this;
    }

    public function linkGoogle(string $googleId): static
    {
        $this->google_id_hash = static::lookupHash('google', $googleId);

        return $this;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Emails are sent in the language the person last used on the site. */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email_hash;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new MemberVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new MemberResetPassword($token));
    }

    /**
     * Keyed with the same server-side pepper as ticket codes (separate
     * purpose prefix), so hashes cannot be recomputed from a database copy.
     */
    private static function lookupHash(string $purpose, string $value): string
    {
        $key = (string) config('aisa.tickets.pepper');
        if ($key === '') {
            $key = hash('sha256', 'aisa-ticket-pepper|'.Str::after((string) config('app.key'), 'base64:'));
        }

        return hash_hmac('sha256', "member-{$purpose}|{$value}", $key);
    }
}
