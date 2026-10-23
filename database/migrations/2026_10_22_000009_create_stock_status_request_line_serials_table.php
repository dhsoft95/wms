<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_status_request_line_serials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_status_request_line_id');
            $table->foreign('stock_status_request_line_id', 'ssr_line_serials_line_id_fk')
                ->references('id')->on('stock_status_request_lines')->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained('serial_numbers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_status_request_line_serials');
    }
};
