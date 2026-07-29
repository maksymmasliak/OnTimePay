<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'failed_payment', 'collections'])->default('draft');
            $table->decimal('total_amount', 9, 2)->default(0);
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index('company_id', 'invoices_company_id_index');
            $table->index('client_id', 'invoices_client_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
