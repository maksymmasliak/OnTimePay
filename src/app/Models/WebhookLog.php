<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class WebhookLog extends Model
{
    protected $fillable = [
        'stripe_event_id',
        'payload',
        'status',
    ];
}
