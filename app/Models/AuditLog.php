<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who performed the action, honest about entries without a current account. */
    public function actorLabel(): string
    {
        return match (true) {
            $this->user !== null => $this->user->name,
            str_starts_with($this->action, 'retention.') => 'system (scheduled)',
            $this->action === 'auth.failed' => 'unknown (failed sign-in)',
            default => 'deleted account',
        };
    }
}
