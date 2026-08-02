<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_draft_invoice_dispatches_job(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertOk();

        Queue::assertPushed(SendInvoiceJob::class, function (SendInvoiceJob $job) use ($invoice) {
            return $job->invoice->id === $invoice->id;
        });
    }

    public function test_sending_non_draft_invoice_fails(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertStatus(422);

        Queue::assertNotPushed(SendInvoiceJob::class);
    }

    public function test_guest_cannot_send_invoice(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertStatus(401);

        Queue::assertNotPushed(SendInvoiceJob::class);
    }

    public function test_user_cannot_send_invoice_from_another_company(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($otherCompany)->create();
        $invoice = Invoice::factory()->for($otherCompany)->create([
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/send");

        $response->assertStatus(404);

        Queue::assertNotPushed(SendInvoiceJob::class);
    }
}
