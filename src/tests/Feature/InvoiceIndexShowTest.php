<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIndexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_company_invoices(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $client = Client::factory()->for($company)->create();
        Invoice::factory()->for($company)->count(3)->create(['client_id' => $client->id]);

        $otherClient = Client::factory()->for($otherCompany)->create();
        Invoice::factory()->for($otherCompany)->count(2)->create(['client_id' => $otherClient->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/invoices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_single_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['client_id' => $client->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson(['id' => $invoice->id]);
    }

    public function test_user_cannot_view_invoice_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($otherCompany)->create();
        $foreignInvoice = Invoice::factory()->for($otherCompany)->create(['client_id' => $client->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/invoices/{$foreignInvoice->id}");

        $response->assertStatus(404);
    }

    public function test_guest_cannot_list_invoices(): void
    {
        $response = $this->getJson('/api/invoices');

        $response->assertStatus(401);
    }

    public function test_guest_cannot_view_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['client_id' => $client->id]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(401);
    }
}
