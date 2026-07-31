<?php

namespace App\Policies;

use App\Models\LedgerEntry;
use App\Models\User;

class LedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'owner';
    }

    public function view(User $user, LedgerEntry $ledgerEntry): bool
    {
        return $user->role === 'owner'
            && $user->company_id === $ledgerEntry->company_id;
    }
}
