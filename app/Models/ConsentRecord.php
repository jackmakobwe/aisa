<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $fillable = ['consent_version', 'locale', 'age_group', 'accepted', 'decided_at'];

    protected function casts(): array
    {
        return ['accepted' => 'boolean', 'decided_at' => 'datetime'];
    }
}
