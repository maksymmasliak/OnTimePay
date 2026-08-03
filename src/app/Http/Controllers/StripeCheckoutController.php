<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Stripe\StripeClient;
use Illuminate\Http\JsonResponse;

class StripeCheckoutController extends Controller
{
    public function store(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        if (!in_array($invoice->status, ['sent', 'overdue'])) {
            abort(422, 'Invoice is not payable in its current status.');
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
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
