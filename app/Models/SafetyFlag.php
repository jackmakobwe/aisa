<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A possible risk raised on a conversation. A flag is only ever resolved by
 * a person; nothing in the code resolves one automatically.
 */
class SafetyFlag extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_RESOLVED = 'resolved';

    /** Severity order, used for sorting and escalation. */
    public const LEVELS = ['concern' => 1, 'high' => 2, 'critical' => 3];

    protected $fillable = ['ticket_id', 'message_id', 'level', 'reasons', 'source', 'status', 'raised_at'];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'raised_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'outcome' => 'encrypted',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isOpen(): bool
    {
        return $this->status !== self::STATUS_RESOLVED;
    }
}
