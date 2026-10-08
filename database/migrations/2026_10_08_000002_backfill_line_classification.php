<?php

use App\Models\Document;
use App\Services\ItemClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How each document line already on file was classified.
 *
 * The columns arrive in the previous migration; this one fills them, so a line
 * written before the classification existed still knows the paper it belongs to.
 * A line keeps the paper it was actually written on — the document's type — even
 * where that disagrees with what today's rule would decide, because the register
 * is a record of papers that were raised, not a re-derivation of them. The
 * consumable answer that paper stands for is written beside it, so the register
 * can be filtered by consumable / non-consumable the same way for old and new
 * papers alike.
 *
 * Worked paper by paper — one UPDATE per document for all of its lines — rather
 * than joining `documents` into an UPDATE, which SQLite (the test database) does
 * not support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Document::query()
            ->whereIn('type', ItemClassifier::TYPES)
            ->select('id', 'type')
            ->chunkById(200, function ($documents) {
                foreach ($documents as $document) {
                    DB::table('document_items')
                        ->where('document_id', $document->id)
                        ->update([
                            'document_type' => $document->type,
                            'is_consumable' => $document->type === 'RIS',
                        ]);
                }
            });
    }

    public function down(): void
    {
        // A backfill of derived data: the columns themselves are dropped by the
        // migration that added them, so there is nothing to undo here.
    }
};
