<?php

namespace App\Jobs;

use App\Enums\DunningReminderType;
use App\Mail\ReminderMail;
use App\Models\DunningLog;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Invoice $invoice,
        public DunningReminderType $reminderType,
        public DunningLog $log,
    ) {
    }

    public function handle(): void
    {
        try {
            Mail::to($this->invoice->client->email)
                ->send(new ReminderMail($this->invoice, $this->reminderType));

            $this->log->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            $this->log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
