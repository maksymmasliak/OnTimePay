<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DunningLog;
use App\Models\Invoice;
use App\Models\Scopes\CompanyScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createSentInvoice(Company $company, Carbon $dueDate): Invoice
    {
        $client = Client::factory()->for($company)->create();

        return Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'due_date' => $dueDate,
        ]);
    }

    private function dunningLogCount(Invoice $invoice, ?string $type = null): int
    {
        $query = DunningLog::withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id);

        if ($type !== null) {
            $query->where('reminder_type', $type);
        }

        return $query->count();
    }

    public function test_invoice_marked_overdue_on_due_date(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDay());

        $this->artisan('app:process-overdue-invoices');

        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);
        $this->assertEquals(1, $this->dunningLogCount($invoice, 'overdue'));
    }

    public function test_reminder_1_sent_after_two_runs_at_three_days_overdue(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(3));

        $this->artisan('app:process-overdue-invoices');
        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);
        $this->assertEquals(0, $this->dunningLogCount($invoice, 'reminder_1'));

        $this->artisan('app:process-overdue-invoices');
        $this->assertEquals(1, $this->dunningLogCount($invoice, 'reminder_1'));
    }

    public function test_reminder_2_and_collections_after_three_runs_at_seven_days_overdue(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(7));

        $this->artisan('app:process-overdue-invoices'); // sent -> overdue
        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);

        $this->artisan('app:process-overdue-invoices'); // -> reminder_1
        $this->assertEquals('overdue', $invoice->fresh()->status);
        $this->assertEquals(1, $this->dunningLogCount($invoice, 'reminder_1'));

        $this->artisan('app:process-overdue-invoices'); // -> reminder_2 + collections
        $invoice->refresh();
        $this->assertEquals('collections', $invoice->status);
        $this->assertEquals(1, $this->dunningLogCount($invoice, 'reminder_2'));
    }

    public function test_running_command_repeatedly_does_not_duplicate_reminders(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(3));

        $this->artisan('app:process-overdue-invoices');
        $this->artisan('app:process-overdue-invoices');
        $countAtSteadyState = $this->dunningLogCount($invoice);

        $this->artisan('app:process-overdue-invoices');
        $countAfterExtraRun = $this->dunningLogCount($invoice);

        $this->assertEquals(2, $countAtSteadyState);
        $this->assertEquals($countAtSteadyState, $countAfterExtraRun);
    }

    public function test_escalation_progresses_as_days_pass(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $client = Client::factory()->for($company)->create();
        $invoice = Invoice::factory()->for($company)->create([
            'client_id' => $client->id,
            'status' => 'sent',
            'due_date' => now(),
        ]);

        Carbon::setTestNow(now()->addDay());
        $this->artisan('app:process-overdue-invoices');
        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);

        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('app:process-overdue-invoices');

        Carbon::setTestNow(now()->addDays(7));
        $this->artisan('app:process-overdue-invoices');
        $invoice->refresh();
        $this->assertEquals('collections', $invoice->status);

        $this->assertEquals(3, $this->dunningLogCount($invoice));
    }

    public function test_invoice_not_due_yet_is_untouched(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->addDays(5));

        $this->artisan('app:process-overdue-invoices');

        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);
        $this->assertEquals(0, $this->dunningLogCount($invoice));
    }

    public function test_paid_invoice_is_not_touched_even_if_overdue(): void
    {

        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(10));
        $invoice->update(['status' => 'paid']);

        $this->artisan('app:process-overdue-invoices');

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $this->dunningLogCount($invoice));
    }
}
