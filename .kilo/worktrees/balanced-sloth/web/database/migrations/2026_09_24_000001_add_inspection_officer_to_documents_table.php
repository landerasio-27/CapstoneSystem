<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name typed on the Inspection and Acceptance Report's "Inspection Officer"
 * signature line.
 *
 * Until now that line printed whoever approved or prepared the document. It is
 * now something the office records, with that same fallback used only when the
 * field is left blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('inspection_officer')->nullable()->after('acceptance_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('inspection_officer');
        });
    }
};
