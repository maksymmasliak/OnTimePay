<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\LedgerEntry;
use App\Models\Scopes\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use App\Enums\LedgerEntryType;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $log = WebhookLog::firstOrCreate(
                ['stripe_event_id' => $event->id],
                [
                    'type' => $event->type,
                    'payload' => $event->toArray(),
                    'status' => 'pending',
                ]
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // race condition: інший процес щойно вставив цей самий event_id паралельно
            $log = WebhookLog::where('stripe_event_id', $event->id)->firstOrFail();
        }

        if ($log->status === 'processed') {
            return response()->json(['status' => 'already_processed'], 200);
        }

        if ($event->type !== 'checkout.session.completed') {
            $log->update(['status' => 'ignored']);
            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            DB::transaction(function () use ($event, $log) {
                $session = $event->data->object;
                $invoiceId = $session->metadata->invoice_id ?? null;
                $companyId = $session->metadata->company_id ?? null;

                if (!$invoiceId || !$companyId) {
                    throw new \RuntimeException('Missing invoice_id or company_id in metadata');
                }

                $invoice = Invoice::withoutGlobalScope(CompanyScope::class)
                    ->lockForUpdate()
                    ->findOrFail($invoiceId);

                if ($invoice->company_id !== (int) $companyId) {
                    throw new \RuntimeException('Invoice company mismatch — possible tampering.');
                }

                $payment = new Payment([
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id,
                    'amount' => $session->amount_total / 100,
                    'stripe_payment_intent_id' => $session->payment_intent,
                    'status' => 'succeeded',
                ]);
                $payment->company_id = $invoice->company_id;
                $payment->save();

                $ledgerEntry = new LedgerEntry([
                    'invoice_id' => $invoice->id,
                    'amount' => $session->amount_total / 100,
                    'type'  => LedgerEntryType::Payment,
                ]);
                $ledgerEntry->company_id = $invoice->company_id;
                $ledgerEntry->save();

                $invoice->update(['status' => 'paid']);

                $log->update(['status' => 'processed']);
            });
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('Stripe webhook processing failed', ['event_id' => $event->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
