<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'issue_date',
        'due_date',
    ];
}
