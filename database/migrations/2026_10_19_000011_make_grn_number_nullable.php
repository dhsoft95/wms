<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->string('number', 30)->nullable(false)->change();
        });
    }
};
