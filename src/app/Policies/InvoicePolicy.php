<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id
            && $invoice->status === 'draft';
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id
            && $invoice->status === 'draft';
    }

    public function viewLedger(User $user, Invoice $invoice): bool
    {
        // Deliberately narrower than checkout creation (StripeCheckoutController::store(),
        // authorized against 'view'): any company member can request payment
        // for an invoice, but only an owner can see the company's aggregated
        // financial balance. See StripeCheckoutController::store() for the
        // other side of this decision.
        return $user->company_id === $invoice->company_id
            && $user->role === 'owner';
    }
}
