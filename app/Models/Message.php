<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    public const SENDER_USER = 'user';

    public const SENDER_AI = 'ai';

    public const SENDER_HUMAN = 'human';

    /** Notices shown to the user, e.g. safety resources. */
    public const SENDER_SYSTEM = 'system';

    protected $fillable = ['ticket_id', 'sender', 'body', 'language', 'author_id', 'approved_at', 'is_mock'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'approved_at' => 'datetime',
            'is_mock' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
