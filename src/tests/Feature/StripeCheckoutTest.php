<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Checkout\Session;
use Stripe\Service\CheckoutService;
use Stripe\Service\Checkout\SessionService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_checkout_session_for_sent_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $fakeSession = Session::constructFrom(['url' => 'https://checkout.stripe.com/fake-session']);

        $sessionService = $this->mock(SessionService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('create')->once()->andReturn($fakeSession);
        });

        $checkoutService = $this->mock(CheckoutService::class, function ($mock) use ($sessionService) {
            $mock->sessions = $sessionService;
        });

        $stripeClient = $this->mock(StripeClient::class, function ($mock) use ($checkoutService) {
            $mock->checkout = $checkoutService;
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/checkout");

        $response->assertOk();
        $response->assertJson(['checkout_url' => 'https://checkout.stripe.com/fake-session']);
    }
    public function test_owner_can_create_checkout_session_for_collections_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'collections',
            'total_amount' => 100.00,
        ]);

        $fakeSession = Session::constructFrom(['url' => 'https://checkout.stripe.com/fake-session']);

        $sessionService = $this->mock(SessionService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('create')->once()->andReturn($fakeSession);
        });

        $checkoutService = $this->mock(CheckoutService::class, function ($mock) use ($sessionService) {
            $mock->sessions = $sessionService;
        });

        $stripeClient = $this->mock(StripeClient::class, function ($mock) use ($checkoutService) {
            $mock->checkout = $checkoutService;
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/checkout");

        $response->assertOk();
        $response->assertJson(['checkout_url' => 'https://checkout.stripe.com/fake-session']);
    }

    public function test_cannot_checkout_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/checkout");

        $response->assertStatus(422);
    }

    public function test_guest_cannot_create_checkout_session(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/checkout");

        $response->assertStatus(401);
    }

    public function test_user_cannot_checkout_invoice_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($otherCompany)->create();
        $invoice = Invoice::factory()->for($otherCompany)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/checkout");

        $response->assertStatus(404);
    }
}
