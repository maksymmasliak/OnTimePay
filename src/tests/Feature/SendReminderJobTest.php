<?php

namespace Tests\Feature;

use App\Enums\DunningReminderType;
use App\Jobs\SendReminderJob;
use App\Mail\ReminderMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\DunningLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sends_reminder_mail_and_marks_log_processed(): void
    {
        Mail::fake();

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'overdue',
        ]);

        $log = new DunningLog([
            'invoice_id' => $invoice->id,
            'reminder_type' => DunningReminderType::Reminder1,
            'status' => 'pending',
        ]);
        $log->company_id = $company->id;
        $log->save();

        $this->actingAs($user);

        (new SendReminderJob($invoice, DunningReminderType::Reminder1, $log))->handle();

        $log->refresh();
        $this->assertEquals('processed', $log->status);

        Mail::assertSent(ReminderMail::class, function (ReminderMail $mail) use ($invoice, $client) {
            return $mail->invoice->id === $invoice->id
                && $mail->hasTo($client->email)
                && $mail->reminderType === DunningReminderType::Reminder1;
        });
    }

    public function test_handle_marks_log_failed_on_mail_error(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $client = Client::factory()->for($company)->create(['email' => 'client@example.com']);
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'overdue',
        ]);

        $log = new DunningLog([
            'invoice_id' => $invoice->id,
            'reminder_type' => DunningReminderType::Reminder1,
            'status' => 'pending',
        ]);
        $log->company_id = $company->id;
        $log->save();

        Mail::shouldReceive('to')->andThrow(new \Exception('SMTP connection failed'));

        $this->actingAs($user);

        try {
            (new SendReminderJob($invoice, DunningReminderType::Reminder1, $log))->handle();
        } catch (\Throwable $e) {
            // очікувано — job перекидає виняток далі для retry
        }

        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertNotNull($log->error_message);
    }
}
