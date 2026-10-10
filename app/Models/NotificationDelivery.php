<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = ['channel', 'purpose', 'recipient_hint', 'ticket_id', 'status', 'error'];
}
