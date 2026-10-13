<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_line_id')->constrained('grn_lines')->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('condition', 20)->default('good');
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users');
            $table->string('device', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_receipts');
    }
};
