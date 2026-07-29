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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 9, 2);
            $table->string('stripe_payment_intent_id')->unique();
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('company_id', 'payments_company_id_index');
            $table->index('client_id', 'payments_client_id_index');
            $table->index('invoice_id', 'payments_invoice_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
