<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving for the card documents: a Stock Card (SC) or Property Card (PC)
 * can be filed into an archive year, exactly like a PAR / ICS / RIS document.
 *
 * A plain nullable column rather than a constrained foreign key, so the same
 * migration runs on MySQL and on the SQLite test database. A null archive_id
 * means the card is still in the register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('archive_id')->nullable();
            $blueprint->timestamp('archived_at')->nullable();
            $blueprint->index('archive_id');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $blueprint) {
            $blueprint->dropIndex(['archive_id']);
            $blueprint->dropColumn(['archive_id', 'archived_at']);
        });
    }
};
