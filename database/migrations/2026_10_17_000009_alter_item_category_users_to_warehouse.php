<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('item_category_users', 'virtual_warehouse_id')) {
            return; // fresh install already has warehouse_id from migration 000006
        }

        Schema::table('item_category_users', function (Blueprint $table) {
            // Drop old index and column
            $table->dropIndex('icu_cat_vw_user_unique');
            $table->dropForeign(['virtual_warehouse_id']);
            $table->dropColumn('virtual_warehouse_id');

            // Add new column and index
            $table->foreignId('warehouse_id')->after('item_category_id')->constrained()->cascadeOnDelete();
            $table->unique(['item_category_id', 'warehouse_id', 'user_id'], 'icu_cat_wh_user_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('item_category_users', 'warehouse_id')) {
            return;
        }

        Schema::table('item_category_users', function (Blueprint $table) {
            $table->dropIndex('icu_cat_wh_user_unique');
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
            $table->foreignId('virtual_warehouse_id')->after('item_category_id')->constrained()->cascadeOnDelete();
            $table->unique(['item_category_id', 'virtual_warehouse_id', 'user_id'], 'icu_cat_vw_user_unique');
        });
    }
};
