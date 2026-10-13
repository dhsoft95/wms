<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_receipt_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_receipt_id')->constrained('grn_receipts')->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained('serial_numbers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['grn_receipt_id', 'serial_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_receipt_serials');
    }
};
