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

    public function test_invoice_marked_overdue_on_due_date(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDay());

        $this->artisan('app:process-overdue-invoices');

        $invoice->refresh();
        $this->assertEquals('overdue', $invoice->status);
        $this->assertEquals(
            1,
            DunningLog::withoutGlobalScope(CompanyScope::class)
                ->where('invoice_id', $invoice->id)
                ->where('reminder_type', 'overdue')
                ->count()
        );
    }

    public function test_reminder_1_sent_after_three_days(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(3));

        $this->artisan('app:process-overdue-invoices');

        $this->assertEquals(
            1,
            DunningLog::withoutGlobalScope(CompanyScope::class)
                ->where('invoice_id', $invoice->id)
                ->where('reminder_type', 'reminder_1')
                ->count()
        );
    }

    public function test_reminder_2_and_collections_after_seven_days(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(7));

        $this->artisan('app:process-overdue-invoices');

        $invoice->refresh();
        $this->assertEquals('collections', $invoice->status);
        $this->assertEquals(
            1,
            DunningLog::withoutGlobalScope(CompanyScope::class)
                ->where('invoice_id', $invoice->id)
                ->where('reminder_type', 'reminder_2')
                ->count()
        );
    }

    public function test_running_command_twice_does_not_duplicate_reminders(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->subDays(3));

        $this->artisan('app:process-overdue-invoices');
        $countAfterFirstRun = DunningLog::withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id)
            ->count();

        $this->artisan('app:process-overdue-invoices');
        $countAfterSecondRun = DunningLog::withoutGlobalScope(CompanyScope::class)
            ->where('invoice_id', $invoice->id)
            ->count();

        $this->assertEquals($countAfterFirstRun, $countAfterSecondRun);
        $this->assertGreaterThan(0, $countAfterFirstRun);
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

        $this->assertEquals(
            3,
            DunningLog::withoutGlobalScope(CompanyScope::class)
                ->where('invoice_id', $invoice->id)
                ->count()
        );
    }

    public function test_invoice_not_due_yet_is_untouched(): void
    {
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->createSentInvoice($company, now()->addDays(5));

        $this->artisan('app:process-overdue-invoices');

        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);
        $this->assertEquals(
            0,
            DunningLog::withoutGlobalScope(CompanyScope::class)
                ->where('invoice_id', $invoice->id)
                ->count()
        );
    }
}
