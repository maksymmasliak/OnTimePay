<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Invoice $invoice)
    {
    }

    public function handle(): void
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $this->invoice])->output();

        Mail::to($this->invoice->client->email)->send(new InvoiceMail($this->invoice, $pdf));

        $this->invoice->update(['status' => 'sent']);
    }
}
