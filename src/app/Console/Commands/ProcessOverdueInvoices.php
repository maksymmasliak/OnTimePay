<?php

namespace App\Console\Commands;

use App\Enums\DunningReminderType;
use App\Models\DunningLog;
use App\Models\Invoice;
use App\Models\Scopes\CompanyScope;
use App\Jobs\SendReminderJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdueInvoices extends Command
{
    protected $signature = 'app:process-overdue-invoices';

    protected $description = 'Check overdue invoices and trigger dunning escalation (overdue status, reminders, collections)';

    public function handle(): void
    {
        $invoiceIds = Invoice::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', ['sent', 'overdue'])
            ->where('due_date', '<', now())
            ->pluck('id');

        foreach ($invoiceIds as $invoiceId) {
            DB::transaction(function () use ($invoiceId) {
                $invoice = Invoice::withoutGlobalScope(CompanyScope::class)
                    ->lockForUpdate()
                    ->find($invoiceId);

                if (!$invoice || !in_array($invoice->status, ['sent', 'overdue'], true)) {
                    return;
                }

                $daysOverdue = $invoice->due_date->diffInDays(now());

                if ($invoice->status === 'sent') {
                    $invoice->update(['status' => 'overdue']);
                    $this->logAndNotify($invoice, DunningReminderType::Overdue);

                    return;
                }

                $reminder1Sent = $this->alreadySent($invoice, DunningReminderType::Reminder1);

                if (!$reminder1Sent && $daysOverdue >= 3) {
                    $this->logAndNotify($invoice, DunningReminderType::Reminder1);

                    return;
                }

                $reminder2Sent = $this->alreadySent($invoice, DunningReminderType::Reminder2);

                if ($reminder1Sent && !$reminder2Sent && $daysOverdue >= 7) {
                    $this->logAndNotify($invoice, DunningReminderType::Reminder2);
                    $invoice->update(['status' => 'collections']);
                }
            });
        }

        $this->info("Processed {$invoiceIds->count()} overdue invoice(s).");
    }

    private function alreadySent(Invoice $invoice, DunningReminderType $type): bool
    {
        return DunningLog::withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id)
            ->where('reminder_type', $type)
            ->exists();
    }

    private function logAndNotify(Invoice $invoice, DunningReminderType $type): void
    {
        if ($this->alreadySent($invoice, $type)) {
            return;
        }

        $log = new DunningLog([
            'invoice_id' => $invoice->id,
            'reminder_type' => $type,
            'status' => 'pending',
        ]);
        $log->company_id = $invoice->company_id;
        $log->save();

        SendReminderJob::dispatch($invoice, $type, $log);
    }
}
