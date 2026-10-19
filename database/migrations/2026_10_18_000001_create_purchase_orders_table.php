<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('number')->unique();
            $table->string('customer_po_ref')->nullable();
            $table->string('po_file_path')->nullable();
            $table->string('source')->default('manual');
            $table->string('package_type')->default('loose_cargo');
            $table->foreignId('virtual_warehouse_id')->nullable()->constrained('virtual_warehouses')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->date('expected_date')->nullable();
            $table->foreignId('supplier_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('container_no')->nullable();
            $table->string('seal_no')->nullable();
            $table->string('size_type')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('driver_licence')->nullable();
            $table->string('transporter_name')->nullable();
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->foreignId('original_do_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
