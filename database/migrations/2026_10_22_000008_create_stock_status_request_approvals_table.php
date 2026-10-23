<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_status_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_status_request_id')->constrained('stock_status_requests')->cascadeOnDelete();
            $table->foreignId('approval_chain_id')->nullable()->constrained('approval_chains')->nullOnDelete();
            $table->integer('level');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');   // approved | rejected
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_status_request_approvals');
    }
};
