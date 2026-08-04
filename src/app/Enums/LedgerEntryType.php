<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
}
