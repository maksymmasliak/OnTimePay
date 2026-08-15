<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create([
            'password' => Hash::make('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_logout_revokes_current_token(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    public function test_user_can_register_new_company_and_becomes_owner(): void
    {
        $response = $this->postJson('/api/register', [
            'company_name' => 'Test Company LLC',
            'name' => 'Maksym Owner',
            'email' => 'owner@testcompany.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()->assertJsonStructure(['token']);

        $this->assertDatabaseHas('companies', ['name' => 'Test Company LLC']);

        $user = User::where('email', 'owner@testcompany.test')->first();
        $this->assertNotNull($user);
        $this->assertEquals('owner', $user->role);
        $this->assertEquals('Test Company LLC', $user->company->name);
    }

    public function test_registration_token_grants_immediate_access(): void
    {
        $response = $this->postJson('/api/register', [
            'company_name' => 'Immediate Access Co',
            'name' => 'Owner Name',
            'email' => 'immediate@testcompany.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $token = $response->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/invoices')
            ->assertOk();
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        $company = Company::factory()->create();
        $existingUser = User::factory()->for($company)->create();

        $response = $this->postJson('/api/register', [
            'company_name' => 'Another Company',
            'name' => 'Someone Else',
            'email' => $existingUser->email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_registration_fails_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/register', [
            'company_name' => 'Mismatch Co',
            'name' => 'Test User',
            'email' => 'mismatch@testcompany.test',
            'password' => 'secret123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@testcompany.test']);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_registration_ignores_role_from_request_body(): void
    {
        $response = $this->postJson('/api/register', [
            'company_name' => 'Sneaky Co',
            'name' => 'Sneaky User',
            'email' => 'sneaky@testcompany.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'manager',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'sneaky@testcompany.test')->first();
        $this->assertEquals('owner', $user->role);
    }

    public function test_registration_is_throttled_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'company_name' => "Throttle Co {$i}",
                'name' => 'Test User',
                'email' => "duplicate@testcompany.test",
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/register', [
            'company_name' => 'Throttle Co Final',
            'name' => 'Test User',
            'email' => 'duplicate@testcompany.test',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(429);
    }
}
