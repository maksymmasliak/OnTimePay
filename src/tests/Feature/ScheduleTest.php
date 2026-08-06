<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_process_overdue_invoices_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list');

        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command, 'app:process-overdue-invoices'));

        $this->assertCount(1, $events);
        $this->assertEquals('0 1 * * *', $events->first()->expression);
    }

    public function test_reconcile_ledger_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list');

        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command, 'app:reconcile-ledger'));

        $this->assertCount(1, $events);
        $this->assertEquals('0 2 * * *', $events->first()->expression);
    }
}
