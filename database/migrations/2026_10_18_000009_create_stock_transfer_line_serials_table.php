<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_transfer_line_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_line_id')->constrained('stock_transfer_lines')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->timestamps();

            $table->index('stock_transfer_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_line_serials');
    }
};
