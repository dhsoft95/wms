<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_chains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->cascadeOnDelete();
            $table->string('document_type', 20); // po, mrn, transfer
            $table->unsignedTinyInteger('level');
            $table->timestamps();

            $table->unique(['department_id', 'document_type', 'level']);
            $table->index(['department_id', 'document_type']);
        });

        Schema::create('approval_chain_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_chain_id')
                ->constrained('approval_chains')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['approval_chain_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chain_users');
        Schema::dropIfExists('approval_chains');
    }
};
