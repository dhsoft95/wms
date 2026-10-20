<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();

            $table->unsignedBigInteger('dispatch_id')->nullable();
            $table->foreign('dispatch_id')->references('id')->on('dispatches')->nullOnDelete();

            $table->unsignedBigInteger('delivery_order_id')->nullable();
            $table->foreign('delivery_order_id')->references('id')->on('delivery_orders')->nullOnDelete();

            $table->string('status', 30)->default('generated');

            $table->unsignedBigInteger('from_warehouse_id')->nullable();
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->nullOnDelete();

            $table->string('vehicle_reg_no', 30)->nullable();
            $table->string('container_no', 50)->nullable();
            $table->string('container_size', 20)->nullable();
            $table->string('seal_no', 50)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->string('transporter_name', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->text('internal_notes')->nullable();

            $table->unsignedBigInteger('generated_by')->nullable();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();

            $table->string('pod_received_by', 150)->nullable();
            $table->timestamp('pod_received_at')->nullable();
            $table->string('pod_file_path', 255)->nullable();

            $table->text('cancel_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedInteger('printed_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_delivery_notes');
    }
};
