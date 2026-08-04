<?php

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_ledger_balance(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'paid',
            'total_amount' => 150.00,
        ]);

        $entry1 = new LedgerEntry([
            'invoice_id' => $invoice->id,
            'amount' => 100.00,
            'type' => LedgerEntryType::Payment,
        ]);
        $entry1->company_id = $company->id;
        $entry1->save();

        $entry2 = new LedgerEntry([
            'invoice_id' => $invoice->id,
            'amount' => 50.00,
            'type' => LedgerEntryType::Payment,
        ]);
        $entry2->company_id = $company->id;
        $entry2->save();

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/invoices/{$invoice->id}/ledger");

        $response->assertOk();
        $response->assertJson([
            'invoice_id' => $invoice->id,
            'balance' => '150.00',
        ]);
    }

    public function test_manager_cannot_view_ledger_balance(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/invoices/{$invoice->id}/ledger");

        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_ledger_balance(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}/ledger");

        $response->assertStatus(401);
    }

    public function test_owner_cannot_view_ledger_of_another_companys_invoice(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $client = Client::factory()->for($otherCompany)->create();
        $invoice = Invoice::factory()->for($otherCompany)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/invoices/{$invoice->id}/ledger");

        $response->assertStatus(404);
    }
}
