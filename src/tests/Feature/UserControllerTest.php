<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_manager(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/users', [
            'name' => 'New Manager',
            'email' => 'manager@testcompany.test',
            'role' => 'manager',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'name', 'email', 'temporary_password']);

        $this->assertDatabaseHas('users', [
            'email' => 'manager@testcompany.test',
            'company_id' => $company->id,
            'role' => 'manager',
        ]);
    }

    public function test_owner_can_invite_another_owner(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/users', [
            'name' => 'Co Owner',
            'email' => 'coowner@testcompany.test',
            'role' => 'owner',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'coowner@testcompany.test',
            'role' => 'owner',
        ]);
    }

    public function test_manager_cannot_invite_users(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/users', [
            'name' => 'Someone',
            'email' => 'someone@testcompany.test',
            'role' => 'manager',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_cannot_invite_users(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Someone',
            'email' => 'someone@testcompany.test',
            'role' => 'manager',
        ]);

        $response->assertStatus(401);
    }

    public function test_temporary_password_actually_logs_in(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();

        $createResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/users', [
            'name' => 'New Manager',
            'email' => 'realmanager@testcompany.test',
            'role' => 'manager',
        ]);

        $tempPassword = $createResponse->json('temporary_password');

        \Illuminate\Support\Facades\Auth::shouldUse('web');

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'realmanager@testcompany.test',
            'password' => $tempPassword,
        ]);

        $loginResponse->assertOk()->assertJsonStructure(['token']);
    }

    public function test_owner_can_list_company_users(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();
        User::factory()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_owner_list_does_not_include_other_companies_users(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $ownerA = User::factory()->owner()->for($companyA)->create();
        User::factory()->for($companyB)->create();

        $response = $this->actingAs($ownerA, 'sanctum')->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_manager_cannot_list_users(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_manager_can_view_own_profile(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->getJson("/api/users/{$manager->id}");

        $response->assertOk();
    }

    public function test_manager_cannot_view_colleagues_profile(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();
        $colleague = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->getJson("/api/users/{$colleague->id}");

        $response->assertStatus(403);
    }

    public function test_viewing_user_from_another_company_returns_403_not_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $ownerA = User::factory()->owner()->for($companyA)->create();
        $userB = User::factory()->for($companyB)->create();

        $response = $this->actingAs($ownerA, 'sanctum')->getJson("/api/users/{$userB->id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_promote_manager_to_owner(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'role' => 'owner',
        ]);

        $response->assertOk();
        $this->assertEquals('owner', $manager->fresh()->role);
    }

    public function test_manager_cannot_promote_self_to_owner(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'role' => 'owner',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('manager', $manager->fresh()->role);
    }

    public function test_manager_can_update_own_name_without_touching_role(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Name', $manager->fresh()->name);
    }

    public function test_last_owner_cannot_demote_self(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/users/{$owner->id}", [
            'role' => 'manager',
        ]);

        $response->assertStatus(422);
        $this->assertEquals('owner', $owner->fresh()->role);
    }

    public function test_owner_can_demote_self_when_another_owner_exists(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();
        User::factory()->owner()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/users/{$owner->id}", [
            'role' => 'manager',
        ]);

        $response->assertOk();
        $this->assertEquals('manager', $owner->fresh()->role);
    }

    public function test_owner_can_delete_manager(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/users/{$manager->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
    }

    public function test_owner_cannot_delete_self(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/users/{$owner->id}");

        $response->assertStatus(403);
    }

    public function test_manager_cannot_delete_anyone(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();
        $colleague = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->deleteJson("/api/users/{$colleague->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_change_own_password(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();
    }

    public function test_new_password_actually_logs_in(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        \Illuminate\Support\Facades\Auth::shouldUse('web');

        $response = $this->postJson('/api/login', [
            'email' => $manager->email,
            'password' => 'newpassword123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'current_password' => 'password',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_password_change_requires_current_password(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
    }

    public function test_password_change_fails_with_wrong_current_password(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($manager, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'current_password' => 'totally-wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('password', $manager->fresh()->password));
    }

    public function test_owner_resetting_others_password_does_not_need_current_password(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->owner()->for($company)->create();
        $manager = User::factory()->for($company)->create();

        $response = $this->actingAs($owner, 'sanctum')->patchJson("/api/users/{$manager->id}", [
            'password' => 'resetbyowner123',
            'password_confirmation' => 'resetbyowner123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('resetbyowner123', $manager->fresh()->password));
    }
}
