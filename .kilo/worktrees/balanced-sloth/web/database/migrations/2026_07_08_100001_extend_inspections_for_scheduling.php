<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the strict FK so inspector_id can become nullable (a scheduled
        // inspection has no inspector until a staff member claims/starts it).
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropForeign(['inspector_id']);
        });

        // Loosen existing columns for the full scheduling lifecycle.
        // Uses the schema builder (not raw MySQL ALTER) so the migration also
        // runs on the SQLite database used by the test suite.
        Schema::table('inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('inspector_id')->nullable()->change();
            $table->timestamp('started_at')->nullable()->change();
            // status: enum(in_progress, completed) -> string(upcoming/pending/in_progress/completed/cancelled)
            $table->string('status', 32)->default('pending')->change();
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->foreign('inspector_id')->references('id')->on('users')->nullOnDelete();

            $table->foreignId('assigned_staff_id')->nullable()->after('inspector_id')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('assigned_staff_id')->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->boolean('is_urgent')->default(false)->after('scheduled_at');
            $table->string('scope')->nullable()->after('is_urgent'); // teacher|multiple|room|all
            $table->text('notes')->nullable()->after('scope');
            $table->timestamp('completed_at')->nullable()->after('submitted_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('completed_at');

            $table->index('scheduled_at');
            $table->index('assigned_staff_id');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_staff_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['scheduled_at', 'is_urgent', 'scope', 'notes', 'completed_at', 'reminder_sent_at']);
        });

        Schema::table('inspections', function (Blueprint $table) {
            $table->string('status', 32)->default('in_progress')->change();
        });
    }
};
