<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A crisis or referral contact. Only verified entries are ever shown to the
 * public. Starter entries can be imported (aisa:import-contacts) but always
 * arrive unverified: each one must be checked by a person before it shows.
 */
class CrisisResource extends Model
{
    public const CATEGORIES = ['emergency', 'gbv', 'child', 'health', 'mental_health', 'legal', 'counselling', 'community'];

    public const REVIEW_AFTER_DAYS = 90;

    protected $fillable = [
        'category', 'name', 'phone', 'url', 'address', 'district', 'region', 'latitude', 'longitude',
        'description_en', 'description_sw', 'hours', 'source_note', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * A Google Maps link that opens the place in the Maps app or website.
     * It is a plain link: Google is contacted only if the person taps it.
     * Exact coordinates win; otherwise Maps searches for the name and
     * address. National services (no region) get no link: their "address"
     * is guidance such as "at most police stations", not a place.
     */
    public function mapsUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            $query = $this->latitude.','.$this->longitude;
        } elseif (filled($this->address) && filled($this->region)) {
            $query = implode(', ', array_filter([$this->name, $this->address, $this->district, $this->region, 'Tanzania']));
        } else {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
    }

    /** "Kinondoni, Dar es Salaam", or null for national services. */
    public function place(): ?string
    {
        $parts = array_filter([$this->district, $this->region]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /** National services plus those in the given region. */
    public function scopeForRegion(Builder $query, ?string $region): Builder
    {
        return $region === null ? $query : $query->where(fn ($q) => $q->whereNull('region')->orWhere('region', $region));
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
