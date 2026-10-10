<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    public const TYPES = ['concern', 'inappropriate', 'feedback', 'access', 'other'];

    protected $fillable = ['type', 'message', 'contact', 'locale', 'status'];

    protected function casts(): array
    {
        return ['message' => 'encrypted', 'contact' => 'encrypted', 'handled_at' => 'datetime'];
    }
}
