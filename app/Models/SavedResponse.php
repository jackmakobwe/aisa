<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedResponse extends Model
{
    protected $fillable = ['title', 'body', 'language', 'category', 'created_by'];
}
