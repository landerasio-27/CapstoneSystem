<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->enum('status', ['pending', 'verified', 'missing', 'misplaced', 'invalid'])->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'status']);
            $table->index('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
