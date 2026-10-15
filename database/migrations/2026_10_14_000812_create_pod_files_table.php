<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pod_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proof_of_delivery_id')->constrained('proof_of_deliveries')->cascadeOnDelete();
            $table->string('path');
            $table->string('disk', 20)->default('local');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pod_files');
    }
};
