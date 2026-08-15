<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Stripe\StripeClient;
use Illuminate\Http\JsonResponse;

class StripeCheckoutController extends Controller
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {
    }

    public function store(Invoice $invoice): JsonResponse
    {
        // Deliberately checked against 'view', not restricted to owner: any
        // company member (owner or manager) can create a payment link for an
        // invoice they can already see. This is intentionally narrower than
        // InvoicePolicy::viewLedger() (owner-only) — initiating a collection
        // attempt is treated as a lower-sensitivity action than seeing the
        // company's aggregated financial balance. See InvoicePolicy::viewLedger()
        // for the other side of this decision.
        $this->authorize('view', $invoice);

        if (!in_array($invoice->status, ['sent', 'overdue','collections'])) {
            abort(422, 'Invoice is not payable in its current status.');
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) round($invoice->total_amount * 100),
                    'product_data' => ['name' => "Invoice #{$invoice->id}"],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
            ],
            'success_url' => config('app.url') . "/invoices/{$invoice->id}/thanks",
            'cancel_url' => config('app.url') . "/invoices/{$invoice->id}",
        ]);

        return response()->json(['checkout_url' => $session->url]);
    }
}
