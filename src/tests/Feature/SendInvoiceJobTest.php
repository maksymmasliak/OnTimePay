<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceJob;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sends_mail_with_pdf_and_updates_status(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user);

        (new SendInvoiceJob($invoice))->handle();

        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice, $client) {
            return $mail->invoice->id === $invoice->id
                && $mail->hasTo($client->email)
                && !empty($mail->pdfContent);
        });
    }
}
