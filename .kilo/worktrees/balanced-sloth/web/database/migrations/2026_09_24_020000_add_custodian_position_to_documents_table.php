<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The custodian is now a free-text name, so their position is free text too.
 * The printed PAR/ICS "Position" line used to read the old teacher record's
 * position; it now prefers this column and falls back to the teacher link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('custodian_position')->nullable()->after('custodian');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('custodian_position');
        });
    }
};
