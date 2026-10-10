<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private responder note. Never shown to the user. */
class InternalNote extends Model
{
    protected $fillable = ['ticket_id', 'author_id', 'body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
