<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('virtual_warehouse_id')->constrained('virtual_warehouses')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('consignee_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('delivery_address')->nullable();
            $table->date('required_date')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('allocation_rule', 10)->default('default');
            $table->string('status', 20)->default('draft');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('backorder_of_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'virtual_warehouse_id']);
            $table->index('required_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
