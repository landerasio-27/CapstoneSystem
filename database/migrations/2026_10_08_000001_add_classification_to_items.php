<?php

use App\Models\Equipment;
use App\Models\Supply;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item classification: which paper an item is recorded on.
 *
 * `is_consumable` is what the office answers on the product form; the
 * `document_type` beside it is the derived answer (PAR / ICS / RIS) kept on the
 * row so the lists, the dashboard and the document filters can group and count
 * items without re-deriving the rule in every query. Both are written by the
 * models from App\Services\ItemClassifier, so there is one rule in the system.
 *
 * On a document line the pair records how that line was classified when the
 * paper was written: the paper's type follows from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            // Supplies are the consumable catalogue: a supply is consumed in
            // operations unless the office marks it as property instead.
            $table->boolean('is_consumable')->default(true)->after('category');
            $table->string('document_type', 4)->default('RIS')->after('is_consumable');
        });

        Schema::table('equipment', function (Blueprint $table) {
            // Equipment is property: never consumed.
            $table->boolean('is_consumable')->default(false)->after('category');
            $table->string('document_type', 4)->default('PAR')->after('is_consumable');
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->boolean('is_consumable')->nullable()->after('unit_cost');
            $table->string('document_type', 4)->nullable()->after('is_consumable');
        });

        // Existing rows carry the right classification for their stored cost:
        // saving each one lets the models derive it (and covers equipment whose
        // unit cost puts it on an ICS rather than the column default PAR).
        Supply::query()->each(fn (Supply $supply) => $supply->save());
        Equipment::query()->each(fn (Equipment $equipment) => $equipment->save());

        // The lines already on file are backfilled by the next migration
        // (2026_10_08_000002_backfill_line_classification), which runs after
        // these columns exist and writes each line's paper onto it.
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->dropColumn(['is_consumable', 'document_type']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['is_consumable', 'document_type']);
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->dropColumn(['is_consumable', 'document_type']);
        });
    }
};
