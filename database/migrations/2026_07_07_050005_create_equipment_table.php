<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('property_number')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('unit');
            $table->unsignedInteger('quantity_on_hand')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('status', ['available', 'issued', 'damaged', 'missing', 'misplaced'])->default('available');
            $table->date('date_acquired')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('category');
            $table->index(['room_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
