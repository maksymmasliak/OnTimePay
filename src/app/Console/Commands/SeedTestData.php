<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Console\Command;

class SeedTestData extends Command
{
    protected $signature = 'app:seed-test-data';
    protected $description = 'Create a test company, user, client, and invoice for manual API testing';

    public function handle(): void
    {
        $company = Company::create(['name' => 'Test Company']);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);

        $client = new Client(['name' => 'Test Client', 'email' => 'client@example.com']);
        $client->company_id = $company->id;
        $client->save();

        $invoice = new Invoice([
            'client_id' => $client->id,
            'status' => 'sent',
            'total_amount' => 100.00,
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
        ]);
        $invoice->company_id = $company->id;
        $invoice->save();

        $this->info("USER_EMAIL: {$user->email}");
        $this->info("INVOICE_ID: {$invoice->id}");
    }
}
