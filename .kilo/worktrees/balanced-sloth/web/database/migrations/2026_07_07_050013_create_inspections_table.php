<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('verified_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('misplaced_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['teacher_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
