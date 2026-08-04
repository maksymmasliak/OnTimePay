<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciled_invoice_reports_no_mismatch(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'total_amount' => 100.00,
        ]);

        $entry = new LedgerEntry(['invoice_id' => $invoice->id, 'amount' => 100.00, 'type' => LedgerEntryType::Payment]);
        $entry->company_id = $company->id;
        $entry->save();

        $this->artisan('app:reconcile-ledger')
            ->expectsOutputToContain('found 0 mismatch(es)')
            ->assertExitCode(0);
    }

    public function test_mismatched_invoice_is_detected(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'total_amount' => 100.00,
        ]);

        $entry = new LedgerEntry(['invoice_id' => $invoice->id, 'amount' => 60.00, 'type' => LedgerEntryType::Payment]);
        $entry->company_id = $company->id;
        $entry->save();

        $this->artisan('app:reconcile-ledger')
            ->expectsOutputToContain('found 1 mismatch(es)')
            ->assertExitCode(0);
    }
}
