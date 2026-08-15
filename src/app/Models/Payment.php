<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class Payment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'client_id',
        'invoice_id',
        'amount',
        'stripe_payment_intent_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
