<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Inc']);

        $owner = User::factory()->owner()->for($company)->create([
            'name' => 'Demo Owner',
            'email' => 'owner@example.com',
        ]);

        $client = Client::factory()->for($company)->create([
            'name' => 'Demo Client',
            'email' => 'client@example.com',
        ]);

        Invoice::factory()->for($company)->for($client)->create([
            'status' => 'draft',
            'total_amount' => 0,
        ]);

        Invoice::factory()->for($company)->for($client)->create([
            'status' => 'sent',
            'total_amount' => 250.00,
            'due_date' => now()->addDays(14),
        ]);

        Invoice::factory()->for($company)->for($client)->create([
            'status' => 'overdue',
            'total_amount' => 480.00,
            'due_date' => now()->subDays(5),
        ]);

        $this->command->info("Seeded: {$company->name} — login as {$owner->email} / password");
    }
}
