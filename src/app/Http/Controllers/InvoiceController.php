<?php

namespace App\Http\Controllers;

use App\DTO\InvoiceData;
use App\DTO\InvoiceUpdateData;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use App\Jobs\SendInvoiceJob;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $data = InvoiceData::fromArray($request->validated());

        $invoice = $this->invoiceService->create($data, $request->user());

        return response()->json($invoice, 201);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $data = InvoiceUpdateData::fromArray($request->validated());

        $invoice = $this->invoiceService->update($invoice, $data);

        return response()->json($invoice);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoiceService->delete($invoice);

        return response()->json(null, 204);
    }
    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->status !== 'draft') {
            abort(422, 'Only draft invoices can be sent.');
        }

        SendInvoiceJob::dispatch($invoice);

        return response()->json(['message' => 'Invoice queued for sending.']);
    }
}
