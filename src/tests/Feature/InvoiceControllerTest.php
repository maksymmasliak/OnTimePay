<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_invoice_with_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/invoices', [
            'client_id' => $client->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Дизайн лендінгу', 'quantity' => 2, 'unit_price' => 500],
                ['description' => 'Хостинг на місяць', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertCreated();

        $invoice = Invoice::first();
        $this->assertSame('1100.00', $invoice->total_amount);
        $this->assertCount(2, $invoice->items);
        $this->assertEquals($company->id, $invoice->company_id);
    }

    public function test_money_fields_are_cast_to_decimal_strings(): void
    {
        // Locks in the decimal:2 cast added to Invoice/InvoiceItem/Payment: money
        // fields must come back as fixed-precision strings ("1100.00"), never as
        // float, so nothing downstream silently loses precision on read.
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/invoices', [
            'client_id' => $client->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Консультація', 'quantity' => 3, 'unit_price' => 33.33],
            ],
        ])->assertCreated();

        $invoice = Invoice::with('items')->first();
        $item = $invoice->items->first();

        $this->assertIsString($invoice->total_amount);
        $this->assertSame('99.99', $invoice->total_amount);
        $this->assertIsString($item->quantity);
        $this->assertSame('3.00', $item->quantity);
        $this->assertIsString($item->unit_price);
        $this->assertSame('33.33', $item->unit_price);
    }

    public function test_creating_invoice_with_client_from_another_company_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $foreignClient = Client::factory()->for($otherCompany)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/invoices', [
            'client_id' => $foreignClient->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Щось', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('client_id');
    }

    public function test_guest_cannot_create_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Щось', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_partially_update_invoice_due_date_only(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'draft']);

        $newDate = now()->addDays(30)->toDateString();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/invoices/{$invoice->id}", [
                'due_date' => $newDate,
            ]);

        $response->assertOk();
        $this->assertEquals($newDate, $invoice->fresh()->due_date->toDateString());
    }

    public function test_updating_items_recalculates_total(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'draft']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/invoices/{$invoice->id}", [
                'items' => [
                    ['description' => 'Новий пункт', 'quantity' => 3, 'unit_price' => 50],
                ],
            ]);

        $response->assertOk();
        $this->assertSame('150.00', $invoice->fresh()->total_amount);
        $this->assertCount(1, $invoice->fresh()->items);
    }

    public function test_user_cannot_update_invoice_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $foreignInvoice = Invoice::factory()->for($otherCompany)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/invoices/{$foreignInvoice->id}", [
                'due_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertStatus(404);
    }

    public function test_user_can_delete_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'draft']);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted($invoice);
    }

    public function test_user_cannot_delete_non_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'sent']);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_sent_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'sent']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/invoices/{$invoice->id}", [
                'due_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_paid_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['status' => 'paid']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/invoices/{$invoice->id}", [
                'due_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertStatus(403);
    }
}
