<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;

class InvoiceItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'description',
        'quantity',
        'unit_price',
    ];
}
