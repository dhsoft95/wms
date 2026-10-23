<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_status_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('virtual_warehouse_id')->constrained('virtual_warehouses');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('type');     // mark_obsolete | restore
            $table->string('status')->default('draft');
            $table->text('reason');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_status_requests');
    }
};
