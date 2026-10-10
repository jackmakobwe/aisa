<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A crisis or referral contact. Only verified entries are ever shown to the
 * public. No contacts are seeded: each one must be checked by a person.
 */
class CrisisResource extends Model
{
    public const CATEGORIES = ['emergency', 'mental_health', 'gbv', 'child', 'counselling', 'community'];

    public const REVIEW_AFTER_DAYS = 90;

    protected $fillable = [
        'category', 'name', 'phone', 'url', 'description_en', 'description_sw', 'hours', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function description(string $locale): ?string
    {
        return $locale === 'sw' ? ($this->description_sw ?: $this->description_en) : $this->description_en;
    }

    public function isDueForReview(): bool
    {
        return $this->verified_at === null || $this->verified_at->lt(now()->subDays(self::REVIEW_AFTER_DAYS));
    }
}
