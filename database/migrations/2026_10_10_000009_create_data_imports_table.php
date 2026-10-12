<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);                        // ImportType enum value
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('status', 30)->default('uploaded'); // ImportStatus enum value
            $table->string('mode', 30)->default('create_only');// ImportMode enum value

            // Row counters
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);

            // JSON payloads
            $table->json('errors')->nullable();   // [{row, column, message}]
            $table->json('summary')->nullable();  // {will_create, will_update, …}

            // Users
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
