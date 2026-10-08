<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // opaque token encoded in the QR image
            $table->string('qrable_type'); // App\Models\Teacher | App\Models\Equipment
            $table->unsignedBigInteger('qrable_id');
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->unique(['qrable_type', 'qrable_id']);
            $table->index(['qrable_type', 'qrable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
