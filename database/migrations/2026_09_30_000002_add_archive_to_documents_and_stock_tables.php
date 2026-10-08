<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving: a document (PAR / ICS / RIS) or a stock item (supply / equipment)
 * can be filed into an archive year. Nothing is copied or deleted — the record
 * keeps every trace of its history — it only gains the year it was filed under.
 *
 * archive_id is a plain nullable column rather than a constrained foreign key
 * so the same migration runs on MySQL and on the SQLite test database. A null
 * archive_id means the record is still in the working lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['documents', 'supplies', 'equipment'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('archive_id')->nullable();
                $blueprint->timestamp('archived_at')->nullable();
                $blueprint->index('archive_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['documents', 'supplies', 'equipment'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['archive_id']);
                $blueprint->dropColumn(['archive_id', 'archived_at']);
            });
        }
    }
};
