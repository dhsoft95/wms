<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->string('delivery_note_ref', 100)->nullable()->after('notes');
            $table->date('expected_date')->nullable()->after('delivery_note_ref');
            $table->foreignId('customer_partner_id')->nullable()->constrained('partners')->nullOnDelete()->after('supplier_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->dropForeign(['customer_partner_id']);
            $table->dropColumn(['delivery_note_ref', 'expected_date', 'customer_partner_id']);
        });
    }
};
