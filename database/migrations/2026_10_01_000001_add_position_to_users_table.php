<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The office each account is held in — "Supply Office Administrator",
     * "Administrative Officer I (Supply Officer I)" and the like. It is written
     * by the account holder on their own profile, and stands blank until they
     * write it: a form that prints a position then keeps its own default rather
     * than an invented one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('position')->nullable()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
