<?php

namespace App\Http\Controllers;

use App\Exceptions\WebhookPermanentException;
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
                    throw new WebhookPermanentException('Missing invoice_id or company_id in metadata');
                }

                $invoice = Invoice::withoutGlobalScope(CompanyScope::class)
                    ->lockForUpdate()
                    ->findOrFail($invoiceId);

                if ($invoice->company_id !== (int) $companyId) {
                    throw new WebhookPermanentException('Invoice company mismatch — possible tampering.');
                }

                if ($invoice->status === 'paid') {
                    $log->update(['status' => 'processed', 'error_message' => 'Duplicate payment attempt ignored — invoice already paid.']);
                    Log::warning('Stripe webhook: duplicate payment attempt ignored', ['event_id' => $event->id, 'invoice_id' => $invoice->id]);
                    return;
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
        } catch (WebhookPermanentException $e) {
            // Permanent/business-logic failure (bad metadata, tampering, missing
            // invoice). Retrying won't fix it — acknowledge with 200 so Stripe
            // stops redelivering, but keep the failure visible for manual review.
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('Stripe webhook rejected (permanent error)', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'error' => $e->getMessage()], 200);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('Stripe webhook processing failed (will retry)', ['event_id' => $event->id, 'error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
