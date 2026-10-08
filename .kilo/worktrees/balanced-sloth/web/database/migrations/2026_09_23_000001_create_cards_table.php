<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cards = the digital Stock Card (SC) / Property Card (PC) kept by the supply
 * and property office. A card is a thin header record that points at the item
 * it documents (a supply for SC, a piece of equipment/PPE for PC); the
 * transactions themselves live in card_transactions and are projected from the
 * real ledgers (inventory movements, document items) or posted by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['SC', 'PC']);
            $table->string('card_no')->unique();

            // Exactly one of these is set: SC -> supply, PC -> equipment.
            $table->foreignId('supply_id')->nullable()->constrained('supplies')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();

            // Appendix 54/69 header block.
            $table->string('entity_name')->default('Isabela State University - Cauayan Campus');
            $table->string('fund_cluster')->nullable();

            // SC only: the re-order point printed on the card. The supply form no
            // longer owns this figure, so the card keeps its own copy.
            $table->unsignedInteger('reorder_point')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One card per item: never duplicate the official record.
            $table->unique('supply_id');
            $table->unique('equipment_id');
            $table->index(['type', 'card_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
