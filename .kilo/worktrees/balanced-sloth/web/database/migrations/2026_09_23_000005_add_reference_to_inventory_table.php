<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stock-in carries the document it came in on (IAR no., PO no., delivery
 * receipt, …), typed by the officer recording it.
 *
 * It sits alongside the existing reference_type / reference_id pair, which point
 * at the system record a movement was generated from (a requisition, a document).
 * The Stock Card prints the document number: it prefers the linked record when
 * there is one and falls back to this typed reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
