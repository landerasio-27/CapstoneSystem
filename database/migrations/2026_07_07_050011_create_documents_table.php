<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_no')->unique();
            $table->enum('type', ['PAR', 'IAR', 'ICS', 'RIS']);
            $table->enum('status', ['draft', 'approved', 'issued'])->default('draft');

            // Self-reference: the document this one auto-inherited its data from
            // (e.g. an IAR/ICS generated "from" a PAR keeps the same header + items).
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Optional link back to the originating business record (e.g. a SupplyRequest for a RIS).
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('entity_name')->default('Isabela State University - Cauayan Campus');
            $table->string('fund_cluster')->nullable();
            $table->date('document_date');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
