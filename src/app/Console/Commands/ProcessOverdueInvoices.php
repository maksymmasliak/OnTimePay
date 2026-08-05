<?php

namespace App\Console\Commands;

use App\Enums\DunningReminderType;
use App\Models\DunningLog;
use App\Models\Invoice;
use App\Models\Scopes\CompanyScope;
use App\Jobs\SendReminderJob;
use Illuminate\Console\Command;

class ProcessOverdueInvoices extends Command
{
    protected $signature = 'app:process-overdue-invoices';

    protected $description = 'Check overdue invoices and trigger dunning escalation (overdue status, reminders, collections)';

    public function handle(): void
    {
        $invoices = Invoice::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', ['sent', 'overdue'])
            ->where('due_date', '<', now())
            ->get();

        foreach ($invoices as $invoice) {
            $daysOverdue = $invoice->due_date->diffInDays(now());

            if ($invoice->status === 'sent') {
                $invoice->update(['status' => 'overdue']);
                $this->logAndNotify($invoice, DunningReminderType::Overdue);
            }

            if ($daysOverdue >= 3) {
                $this->logAndNotify($invoice, DunningReminderType::Reminder1);
            }

            if ($daysOverdue >= 7) {
                $this->logAndNotify($invoice, DunningReminderType::Reminder2);
                $invoice->update(['status' => 'collections']);
            }
        }

        $this->info("Processed {$invoices->count()} overdue invoice(s).");
    }

    private function logAndNotify(Invoice $invoice, DunningReminderType $type): void
    {
        $alreadySent = DunningLog::withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id)
            ->where('reminder_type', $type)
            ->exists();

        if ($alreadySent) {
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
