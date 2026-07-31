<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_invoice_from_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userA = User::factory()->for($companyA)->create();
        $invoiceB = Invoice::factory()->for($companyB)->create();

        $this->actingAs($userA);

        $this->expectException(ModelNotFoundException::class);
        Invoice::findOrFail($invoiceB->id);
    }

    public function test_invoice_created_by_user_gets_correct_company_id(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);

        $client = Client::factory()->for($company)->create();

        $invoice = Invoice::create([
            'client_id' => $client->id,
        ]);

        $this->assertEquals($company->id, $invoice->company_id);
    }

    public function test_company_id_cannot_be_mass_assigned_to_different_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();

        $this->actingAs($userA);

        $client = Client::factory()->for($companyA)->create();

        $invoice = Invoice::create([
            'company_id' => $companyB->id, // спроба підміни
            'client_id' => $client->id,
        ]);

        $this->assertEquals($companyA->id, $invoice->company_id);
    }
}
