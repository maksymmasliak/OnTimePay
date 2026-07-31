<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class LedgerEntry extends Model
{
    use BelongsToCompany;

    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'amount',
        'type',
    ];
}
