<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('approval_chain_id')->nullable();
            $table->foreign('approval_chain_id')->references('id')->on('approval_chains')->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('approver_role', 30)->default('department');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->string('decision', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_approvals');
    }
};
