<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'amount',
        'type',
    ];
}
