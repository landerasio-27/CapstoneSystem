<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Free-text signatory names for PAR/ICS/RIS. When blank the layouts
            // fall back to the linked teacher's name (previous behaviour).
            $table->string('requested_by')->nullable()->after('purpose');
            $table->string('received_by')->nullable()->after('requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['requested_by', 'received_by']);
        });
    }
};
