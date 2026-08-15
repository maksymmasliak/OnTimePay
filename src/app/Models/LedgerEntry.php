<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToCompany;
use App\Enums\LedgerEntryType;

class LedgerEntry extends Model
{
    use BelongsToCompany;

    const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'amount',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
