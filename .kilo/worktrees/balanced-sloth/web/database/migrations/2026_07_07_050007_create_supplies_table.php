<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('unit');
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
