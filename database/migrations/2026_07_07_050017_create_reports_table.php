<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // inventory_summary, stock_movement, supply_issuance, inspection_results, missing_equipment, document_register
            $table->string('format')->default('pdf'); // pdf, xlsx
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
