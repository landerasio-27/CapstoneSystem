<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // IAR-specific header fields
            $table->string('supplier')->nullable()->after('fund_cluster');
            $table->string('po_no')->nullable()->after('supplier');
            $table->date('po_date')->nullable()->after('po_no');
            $table->string('invoice_no')->nullable()->after('po_date');
            $table->date('invoice_date')->nullable()->after('invoice_no');
            $table->string('requisition_office')->nullable()->after('invoice_date');
            $table->string('requisition_center_code')->nullable()->after('requisition_office');
            $table->date('date_inspected')->nullable()->after('requisition_center_code');
            $table->date('date_received')->nullable()->after('date_inspected');
            $table->string('acceptance_status')->nullable()->after('date_received'); // complete | partial

            // RIS-specific header fields
            $table->string('division')->nullable()->after('acceptance_status');
            $table->string('office')->nullable()->after('division');
            $table->string('responsibility_center_code')->nullable()->after('office');
            $table->text('purpose')->nullable()->after('responsibility_center_code');
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->date('date_acquired')->nullable()->after('property_number');       // PAR
            $table->string('estimated_useful_life')->nullable()->after('date_acquired'); // ICS
            $table->string('stock_available')->nullable()->after('estimated_useful_life'); // RIS: yes | no
            $table->unsignedInteger('issued_quantity')->nullable()->after('stock_available'); // RIS
            $table->string('item_remarks')->nullable()->after('issued_quantity');       // RIS
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'supplier', 'po_no', 'po_date', 'invoice_no', 'invoice_date',
                'requisition_office', 'requisition_center_code', 'date_inspected',
                'date_received', 'acceptance_status', 'division', 'office',
                'responsibility_center_code', 'purpose',
            ]);
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->dropColumn([
                'date_acquired', 'estimated_useful_life', 'stock_available',
                'issued_quantity', 'item_remarks',
            ]);
        });
    }
};
