<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
            $table->string('employee_id')->nullable()->unique()->after('name');
            $table->string('contact_number')->nullable()->after('email');
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->boolean('is_active')->default(true)->after('fcm_token');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index('role_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['employee_id', 'contact_number', 'fcm_token', 'is_active', 'last_login_at']);
        });
    }
};
