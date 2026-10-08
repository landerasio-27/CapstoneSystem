<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One posted line on a Stock Card or Property Card.
 *
 * Lines are never edited or deleted: a mistake is corrected by posting an
 * adjustment, which is what the official forms require. `inventory_id` and
 * `document_item_id` record which source row a line was projected from — the
 * unique indexes make the projection idempotent, so re-syncing a card can never
 * double-post a receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();

            $table->date('transaction_date');
            $table->enum('type', ['receipt', 'issue', 'transfer', 'disposal', 'adjustment']);

            // Reference document (IAR/RIS/PAR/ICS no.), typed or resolved from the source.
            $table->string('reference')->nullable();

            $table->unsignedInteger('receipt_qty')->default(0);
            $table->unsignedInteger('issue_qty')->default(0);

            // SC "Office" / PC "Issue/Transfer/Disposal - Office/Officer".
            $table->string('office')->nullable();

            // Running balance after this line. Always calculated, never typed.
            $table->unsignedInteger('balance_qty');

            // PC only: amount of the receipt.
            $table->decimal('amount', 14, 2)->nullable();

            $table->string('remarks')->nullable();

            // Provenance of projected lines (null for hand-posted lines).
            $table->foreignId('inventory_id')->nullable()->constrained('inventory')->nullOnDelete();
            $table->foreignId('document_item_id')->nullable()->constrained('document_items')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('inventory_id');
            $table->unique('document_item_id');
            $table->index(['card_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_transactions');
    }
};
