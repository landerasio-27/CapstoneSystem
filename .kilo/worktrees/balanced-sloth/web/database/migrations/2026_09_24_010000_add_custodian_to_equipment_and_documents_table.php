<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custodian becomes a free-text name on equipment and documents.
 *
 * The old teacher_id dropdown only allowed people who existed in the teachers
 * table, but a custodian can be any office staff member. The columns are kept
 * (deprecated) rather than dropped so existing records and the mobile API stay
 * intact; every read now prefers `custodian` and falls back to the old teacher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('custodian')->nullable()->after('teacher_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('custodian')->nullable()->after('teacher_id');
        });

        // Preserve the names already chosen through the dropdown so no record
        // loses its custodian after the form changes to free text.
        // Done row-by-row in PHP (not UPDATE..JOIN) so it runs on both MySQL
        // and the SQLite test database.
        $names = DB::table('teachers')->get()->mapWithKeys(
            fn ($t) => [$t->id => trim("{$t->first_name} {$t->last_name}")]
        );

        foreach (['equipment', 'documents'] as $table) {
            DB::table($table)
                ->whereNotNull('teacher_id')
                ->whereNull('custodian')
                ->orderBy('id')
                ->each(function ($row) use ($names, $table) {
                    $name = $names->get($row->teacher_id);

                    if ($name !== null) {
                        DB::table($table)->where('id', $row->id)->update(['custodian' => $name]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('custodian');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('custodian');
        });
    }
};
