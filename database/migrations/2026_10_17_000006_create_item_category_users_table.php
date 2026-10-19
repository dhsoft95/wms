<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_category_users')) {
            return;
        }
        Schema::create('item_category_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['item_category_id', 'warehouse_id', 'user_id'], 'icu_cat_wh_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_category_users');
    }
};
