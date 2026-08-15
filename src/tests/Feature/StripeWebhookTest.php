<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\LedgerEntry;
use App\Models\WebhookLog;
use App\Models\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    private function buildSignedPayload(array $eventData): array
    {
        $payload = json_encode($eventData);
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        $header = "t={$timestamp},v1={$signature}";

        return [$payload, $header];
    }

    private function checkoutCompletedEvent(string $eventId, Invoice $invoice, string $paymentIntentId = 'pi_test_123'): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'api_version' => '2026-07-29.dahlia',
            'created' => time(),
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => 'cs_test_' . uniqid(),
                    'object' => 'checkout.session',
                    'amount_total' => (int) round($invoice->total_amount * 100),
                    'payment_intent' => $paymentIntentId,
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'metadata' => [
                        'invoice_id' => (string) $invoice->id,
                        'company_id' => (string) $invoice->company_id,
                    ],
                ],
            ],
        ];
    }

    public function test_valid_webhook_creates_payment_and_marks_invoice_paid(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $event = $this->checkoutCompletedEvent('evt_test_valid_001', $invoice);
        [$payload, $signature] = $this->buildSignedPayload($event);

        $response = $this->call(
            'POST',
            '/api/webhooks/stripe',
            [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'amount' => '100.00',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_valid_001',
            'status' => 'processed',
        ]);
    }

    public function test_duplicate_webhook_does_not_create_second_payment(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $event = $this->checkoutCompletedEvent('evt_test_duplicate_001', $invoice);
        [$payload, $signature] = $this->buildSignedPayload($event);

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk();

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk();

        $this->assertEquals(1, Payment::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals(1, LedgerEntry::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals(1, WebhookLog::where('stripe_event_id', 'evt_test_duplicate_001')->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        $event = $this->checkoutCompletedEvent('evt_test_invalid_sig', $invoice);
        $payload = json_encode($event);

        $response = $this->call(
            'POST',
            '/api/webhooks/stripe',
            [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => 't=' . time() . ',v1=totally_wrong_signature', 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400);
        $this->assertEquals(0, Payment::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals(0, WebhookLog::count());
    }

    public function test_failed_event_is_retried_and_succeeds_on_resend(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $event = $this->checkoutCompletedEvent('evt_test_retry_001', $invoice);
        unset($event['data']['object']['metadata']['company_id']);
        [$payload, $signature] = $this->buildSignedPayload($event);

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk();

        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_retry_001',
            'status' => 'failed',
        ]);
        $this->assertEquals(0, Payment::withoutGlobalScope(CompanyScope::class)->count());

        $fixedEvent = $this->checkoutCompletedEvent('evt_test_retry_001', $invoice);
        [$fixedPayload, $fixedSignature] = $this->buildSignedPayload($fixedEvent);

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $fixedSignature, 'CONTENT_TYPE' => 'application/json'], $fixedPayload)
            ->assertOk();

        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_retry_001',
            'status' => 'processed',
        ]);
        $this->assertEquals(1, Payment::withoutGlobalScope(CompanyScope::class)->count());
    }

    public function test_second_concurrent_checkout_session_does_not_double_pay(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $firstEvent = $this->checkoutCompletedEvent('evt_test_dup_session_1', $invoice, 'pi_test_first');
        [$firstPayload, $firstSignature] = $this->buildSignedPayload($firstEvent);

        $secondEvent = $this->checkoutCompletedEvent('evt_test_dup_session_2', $invoice, 'pi_test_second');
        [$secondPayload, $secondSignature] = $this->buildSignedPayload($secondEvent);

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $firstSignature, 'CONTENT_TYPE' => 'application/json'], $firstPayload)
            ->assertOk();

        $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $secondSignature, 'CONTENT_TYPE' => 'application/json'], $secondPayload)
            ->assertOk();

        $this->assertEquals(1, Payment::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals(1, LedgerEntry::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals('paid', $invoice->fresh()->status);

        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_dup_session_2',
            'status' => 'processed',
        ]);
    }

    public function test_tampered_metadata_acknowledges_with_200_and_does_not_trigger_retry(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $event = $this->checkoutCompletedEvent('evt_test_tampered', $invoice);
        $event['data']['object']['metadata']['company_id'] = (string) $otherCompany->id;
        [$payload, $signature] = $this->buildSignedPayload($event);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertOk();

        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_tampered',
            'status' => 'failed',
        ]);
        $this->assertEquals(0, Payment::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals('sent', $invoice->fresh()->status);
    }

    public function test_nonexistent_invoice_returns_500_to_trigger_stripe_retry(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
        ]);

        $nonExistentInvoiceId = $invoice->id + 999999;

        $event = $this->checkoutCompletedEvent('evt_test_missing_invoice', $invoice);
        $event['data']['object']['metadata']['invoice_id'] = (string) $nonExistentInvoiceId;
        [$payload, $signature] = $this->buildSignedPayload($event);

        $response = $this->call('POST', '/api/webhooks/stripe', [], [], [],
            ['HTTP_STRIPE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload);

        $response->assertStatus(500);

        $this->assertDatabaseHas('webhook_logs', [
            'stripe_event_id' => 'evt_test_missing_invoice',
            'status' => 'failed',
        ]);
        $this->assertEquals(0, Payment::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertEquals('sent', $invoice->fresh()->status);
    }
}
