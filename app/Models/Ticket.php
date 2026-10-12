<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One anonymous conversation. The secret ticket code is never stored;
 * only its keyed hash (code_hash) is.
 */
class Ticket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_AWAITING_HUMAN = 'awaiting_human';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_AWAITING_HUMAN, self::STATUS_ANSWERED, self::STATUS_CLOSED];

    protected $fillable = [
        'reference', 'consent_record_id', 'locale', 'language', 'display_name', 'is_minor',
        'help_mode', 'status', 'category', 'module', 'urgency', 'safety_flagged', 'last_activity_at',
    ];

    protected $hidden = ['code_hash'];

    /** Mirror the column defaults so new, unrefreshed instances are complete. */
    protected $attributes = [
        'locale' => 'en',
        'is_minor' => false,
        'status' => self::STATUS_OPEN,
        'safety_flagged' => false,
        'return_visits' => 0,
    ];

    protected function casts(): array
    {
        return [
            'display_name' => 'encrypted',
            'is_minor' => 'boolean',
            'safety_flagged' => 'boolean',
            'code_issued_at' => 'datetime',
            'human_requested_at' => 'datetime',
            'first_human_reply_at' => 'datetime',
            'last_human_reply_at' => 'datetime',
            'last_user_message_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    /** The optional account this conversation is saved to. Null for anonymous conversations. */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class)->latest('id');
    }

    public function latestTurnAnalysis(): HasOne
    {
        return $this->hasOne(AiAnalysis::class)->where('kind', AiAnalysis::KIND_TURN)->latestOfMany();
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(AiAnalysis::class)->where('kind', AiAnalysis::KIND_DRAFT)->latestOfMany();
    }

    public function safetyFlags(): HasMany
    {
        return $this->hasMany(SafetyFlag::class)->latest('raised_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InternalNote::class)->latest('id');
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(DeletionRequest::class);
    }

    public function consentRecord(): BelongsTo
    {
        return $this->belongsTo(ConsentRecord::class);
    }

    public function hasCode(): bool
    {
        return $this->code_hash !== null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function wantsHuman(): bool
    {
        return $this->help_mode === 'human';
    }

    public function hasPendingDeletion(): bool
    {
        return $this->deletionRequests()->where('status', DeletionRequest::STATUS_PENDING)->exists();
    }

    /** Minutes the user has been waiting for a human reply, or null if not waiting. */
    public function waitingMinutes(): ?int
    {
        if ($this->status !== self::STATUS_AWAITING_HUMAN) {
            return null;
        }

        $since = $this->last_user_message_at ?? $this->human_requested_at;
        if ($this->human_requested_at && $since && $since->lt($this->human_requested_at)) {
            $since = $this->human_requested_at;
        }

        return $since ? (int) $since->diffInMinutes(now()) : null;
    }
}
