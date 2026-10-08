<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two accounts this office hands out are named as the office names them:
 * Admin, who manages everything, and Supply Staff, who works the register.
 *
 * Only the names shown to the reader change. The role's own name — the string
 * the routes, the middleware and the mobile app match on — is left alone, so
 * nothing that already holds a role has to be touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'admin')->update([
            'display_name' => 'Admin',
        ]);

        DB::table('roles')->where('name', 'inspection_staff')->update([
            'display_name' => 'Supply Staff',
            'description' => 'Supply office staff account: the register and the reports, without the administration.',
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'admin')->update([
            'display_name' => 'Administrator',
        ]);

        DB::table('roles')->where('name', 'inspection_staff')->update([
            'display_name' => 'Inspection Staff',
            'description' => 'Non-administrator staff account for the mobile app.',
        ]);
    }
};
