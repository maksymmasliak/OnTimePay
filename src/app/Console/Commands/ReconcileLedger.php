<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Scopes\CompanyScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileLedger extends Command
{
    protected $signature = 'app:reconcile-ledger';

    protected $description = 'Check that paid invoices balance matches their ledger entries sum';

    public function handle(): void
    {
        $invoices = Invoice::withoutGlobalScope(CompanyScope::class)
            ->where('status', 'paid')
            ->get();

        $mismatches = 0;

        foreach ($invoices as $invoice) {
            $balance = $invoice->ledgerEntries()
                ->withoutGlobalScope(CompanyScope::class)
                ->sum('amount');

            if (bccomp((string) $balance, (string) $invoice->total_amount, 2) !== 0) {
                $mismatches++;

                Log::warning('Ledger reconciliation mismatch', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'total_amount' => $invoice->total_amount,
                    'ledger_balance' => $balance,
                ]);

                $this->error("Mismatch: invoice #{$invoice->id} — total {$invoice->total_amount}, ledger {$balance}");
            }
        }

        $this->info("Checked {$invoices->count()} paid invoices, found {$mismatches} mismatch(es).");
    }
}
