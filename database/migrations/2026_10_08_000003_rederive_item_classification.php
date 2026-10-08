<?php

use App\Models\Equipment;
use App\Models\Supply;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-derive the classification the catalogue carries.
 *
 * `document_type` on an item is derived data: it is the paper today's rule would
 * put the item on, read off its consumable answer and its unit cost. When the
 * Property Classification Threshold moves (₱50,000 now), a stored classification
 * can be left saying what the old rule decided — an item at ₱50,000 marked PAR
 * while the rule would now call it an ICS.
 *
 * This migration simply saves every item, which re-runs the models' own
 * derivation, so the whole catalogue agrees with the rule again. Stock, prices
 * and dates are untouched: only the derived paper is recomputed.
 *
 * Lines already written keep the paper they were written on (see
 * 2026_10_08_000002): a document is a record of a paper that was raised, not a
 * re-derivation of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Supply::query()->each(fn (Supply $supply) => $supply->save());
        Equipment::query()->each(fn (Equipment $equipment) => $equipment->save());
    }

    public function down(): void
    {
        // Nothing to undo: this migration writes no new data, it only re-derives
        // the classification of items that already exist.
    }
};
