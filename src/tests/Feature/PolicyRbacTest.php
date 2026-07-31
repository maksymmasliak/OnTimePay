<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_delete_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['client_id' => $client->id, 'status' => 'draft']);

        $this->actingAs($manager);

        $this->assertTrue($manager->can('delete', $invoice));
    }

    public function test_manager_cannot_delete_sent_invoice(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['client_id' => $client->id, 'status' => 'sent']);

        $this->actingAs($manager);

        $this->assertFalse($manager->can('delete', $invoice));
    }

    public function test_owner_cannot_delete_sent_invoice_either(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create(['client_id' => $client->id, 'status' => 'paid']);

        $this->actingAs($owner);

        $this->assertFalse($owner->can('delete', $invoice));
    }

    public function test_manager_cannot_view_ledger_entries(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);

        $this->actingAs($manager);

        $this->assertFalse($manager->can('viewAny', \App\Models\LedgerEntry::class));
    }

    public function test_owner_can_view_ledger_entries(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);

        $this->actingAs($owner);

        $this->assertTrue($owner->can('viewAny', \App\Models\LedgerEntry::class));
    }

    public function test_manager_cannot_create_new_user(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);

        $this->actingAs($manager);

        $this->assertFalse($manager->can('create', User::class));
    }

    public function test_owner_can_create_new_user(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);

        $this->actingAs($owner);

        $this->assertTrue($owner->can('create', User::class));
    }

    public function test_manager_can_update_own_profile_but_not_others(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create(['role' => 'manager']);
        $anotherUser = User::factory()->for($company)->create(['role' => 'manager']);

        $this->actingAs($manager);

        $this->assertTrue($manager->can('update', $manager));
        $this->assertFalse($manager->can('update', $anotherUser));
    }
}
