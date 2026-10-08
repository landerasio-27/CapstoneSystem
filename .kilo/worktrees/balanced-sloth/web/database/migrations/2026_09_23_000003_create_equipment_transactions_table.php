<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One recorded receipt (or issue/transfer/disposal/adjustment) for a piece of
 * equipment.
 *
 * This is the equipment equivalent of the `inventory` ledger that supplies
 * already have: the equipment row keeps the master information plus the current
 * quantity and cost, while every quantity change and the price it was received
 * at is stored here, one row per transaction, permanently.
 *
 * `quantity` is signed (a receipt is positive, an issue negative), so the sum of
 * the ledger is the movement total and `balance_after` is the quantity on hand
 * right after that transaction was recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Date of the transaction (the date on the PAR/ICS/IAR), not the
            // moment it was typed in; created_at keeps that.
            $table->date('transaction_date');
            $table->enum('type', ['receipt', 'issue', 'transfer', 'disposal', 'adjustment'])->default('receipt');

            $table->string('reference')->nullable();   // PAR / ICS / IAR no.
            $table->integer('quantity');               // signed: +received, -issued
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->unsignedInteger('balance_after')->default(0);
            $table->string('source')->nullable();      // supplier / source
            $table->string('remarks')->nullable();

            // Who recorded it. Never typed by hand — always the authenticated user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['equipment_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_transactions');
    }
};
