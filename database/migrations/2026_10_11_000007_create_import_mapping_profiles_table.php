<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_mapping_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('partner_id')->nullable()->references('id')->on('partners')->nullOnDelete();
            $table->string('import_type');
            $table->json('mapping');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['partner_id', 'import_type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_profiles');
    }
};
