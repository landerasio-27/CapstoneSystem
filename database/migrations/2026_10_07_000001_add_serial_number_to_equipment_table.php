<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The serial number the manufacturer puts on the unit, alongside the item code
 * and property number the office assigns.
 *
 * Nullable and free of a unique index: many property items carry none (a
 * whiteboard has no serial), and two records should never be blocked from being
 * saved because someone typed the same serial twice — the office can correct it
 * on the record itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->after('property_number');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }
};
