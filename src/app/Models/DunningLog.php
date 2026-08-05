<?php

namespace App\Models;

use App\Enums\DunningReminderType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DunningLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'invoice_id',
        'reminder_type',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'reminder_type' => DunningReminderType::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
