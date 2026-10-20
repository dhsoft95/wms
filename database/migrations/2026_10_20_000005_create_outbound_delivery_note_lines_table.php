<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_delivery_note_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('odn_id');
            $table->foreign('odn_id')->references('id')->on('outbound_delivery_notes')->cascadeOnDelete();

            $table->unsignedBigInteger('do_line_id')->nullable();
            $table->foreign('do_line_id')->references('id')->on('delivery_order_lines')->nullOnDelete();

            $table->unsignedSmallInteger('line_no');
            $table->string('sku', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty', 12, 4);
            $table->string('uom_code', 20)->nullable();
            $table->json('serial_numbers')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_delivery_note_lines');
    }
};
