<?php

namespace App\Services;

use App\DTO\InvoiceData;
use App\DTO\InvoiceUpdateData;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InvoiceService
{
    public function create(InvoiceData $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user) {
            $invoice = Invoice::create([
                'company_id' => $user->company_id,
                'client_id' => $data->clientId,
                'due_date' => $data->dueDate,
                'status' => 'draft',
            ]);

            $this->syncItems($invoice, $data->items);
            $this->recalculateTotal($invoice);

            return $invoice->fresh('items');
        });
    }

    public function update(Invoice $invoice, InvoiceUpdateData $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update(array_filter([
                'client_id' => $data->clientId,
                'due_date' => $data->dueDate,
            ], fn ($value) => $value !== null));

            if ($data->items !== null) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $data->items);
                $this->recalculateTotal($invoice);
            }

            return $invoice->fresh('items');
        });
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $invoice->items()->create([
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
            ]);
        }
    }

    private function recalculateTotal(Invoice $invoice): void
    {
        $total = $invoice->items()->sum(DB::raw('quantity * unit_price'));

        $invoice->update(['total_amount' => $total]);
    }
}
