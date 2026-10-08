<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock-in transactions carry the cost of that purchase. Issuances
     * (type = out) leave these null because they are not purchases.
     */
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable()->after('quantity');
            $table->decimal('total_cost', 14, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }
};
