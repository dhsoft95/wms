<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('virtual_warehouse_id')->constrained('virtual_warehouses');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('method');       // auction | destroy
            $table->text('reason');
            $table->string('status')->default('draft');
            $table->decimal('total_value', 18, 4)->nullable();

            // Timestamps for workflow steps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('moved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Actor FKs
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Auction completion fields
            $table->string('buyer_name')->nullable();
            $table->decimal('sale_price', 18, 4)->nullable();
            $table->date('sale_date')->nullable();
            $table->string('receipt_file_path')->nullable();

            // Destroy completion fields
            $table->string('destroy_method')->nullable();   // scrap | recycle | return_to_supplier
            $table->date('destroy_date')->nullable();
            $table->string('witness_name')->nullable();
            $table->string('certificate_file_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_requests');
    }
};
