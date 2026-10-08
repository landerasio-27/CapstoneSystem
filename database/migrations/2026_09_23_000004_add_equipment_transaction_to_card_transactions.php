<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a Property Card line remember the equipment receipt it was projected
 * from. The unique index makes the projection idempotent: re-syncing a card can
 * never post the same receipt twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_transactions', function (Blueprint $table) {
            $table->foreignId('equipment_transaction_id')->nullable()->after('document_item_id')
                ->constrained('equipment_transactions')->nullOnDelete();
            $table->unique('equipment_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('card_transactions', function (Blueprint $table) {
            $table->dropUnique(['equipment_transaction_id']);
            $table->dropConstrainedForeignId('equipment_transaction_id');
        });
    }
};
