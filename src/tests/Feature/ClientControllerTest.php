<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_client(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/clients', [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'Acme Corp');
        $this->assertDatabaseHas('clients', [
            'email' => 'billing@acme.test',
            'company_id' => $company->id,
        ]);
    }

    public function test_guest_cannot_create_client(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_email_within_same_company_fails_validation(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        Client::factory()->for($company)->create(['email' => 'billing@acme.test']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/clients', [
            'name' => 'Acme Corp Duplicate',
            'email' => 'billing@acme.test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_same_email_is_allowed_across_different_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        Client::factory()->for($companyB)->create(['email' => 'billing@acme.test']);

        $response = $this->actingAs($userA, 'sanctum')->postJson('/api/clients', [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.test',
        ]);

        $response->assertStatus(201);
    }

    public function test_user_can_list_own_company_clients_only(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        Client::factory()->for($companyA)->create();
        Client::factory()->for($companyB)->create();

        $response = $this->actingAs($userA, 'sanctum')->getJson('/api/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_view_client_from_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->for($companyA)->create();
        $clientB = Client::factory()->for($companyB)->create();

        $response = $this->actingAs($userA, 'sanctum')->getJson("/api/clients/{$clientB->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_update_own_client(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/clients/{$client->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Name');
    }

    public function test_updating_own_email_to_same_value_does_not_fail_uniqueness(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create(['email' => 'billing@acme.test']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/clients/{$client->id}", [
            'name' => 'Acme Renamed',
            'email' => 'billing@acme.test',
        ]);

        $response->assertOk();
    }

    public function test_user_can_delete_client_without_invoices(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_cannot_delete_client_with_invoices(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        Invoice::factory()->for($company)->create(['client_id' => $client->id]);

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }
}
