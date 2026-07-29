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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 9, 2);
            $table->string('type');
            $table->timestamp('created_at')->useCurrent();

            $table->index('company_id', 'ledger_entries_company_id_index');
            $table->index('invoice_id', 'ledger_entries_invoice_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
