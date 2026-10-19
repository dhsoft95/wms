<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate any airfreight package_type rows to loose_cargo
        DB::table('purchase_orders')
            ->where('package_type', 'airfreight')
            ->update(['package_type' => 'loose_cargo']);

        // Add special treatment columns to purchase_orders (guard in case already added manually)
        if (! Schema::hasColumn('purchase_orders', 'is_special_treatment')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->boolean('is_special_treatment')->default(false)->after('notes');
                $table->text('special_treatment_reason')->nullable()->after('is_special_treatment');
            });
        }

        // Add special treatment columns to delivery_orders
        if (! Schema::hasColumn('delivery_orders', 'is_special_treatment')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->boolean('is_special_treatment')->default(false)->after('remarks');
                $table->text('special_treatment_reason')->nullable()->after('is_special_treatment');
            });
        }

        // Add special treatment columns to stock_transfers
        if (! Schema::hasColumn('stock_transfers', 'is_special_treatment')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->boolean('is_special_treatment')->default(false)->after('notes');
                $table->text('special_treatment_reason')->nullable()->after('is_special_treatment');
            });
        }

        // Seed the STN document sequence
        DB::table('document_sequences')->insertOrIgnore([
            'type'         => 'STN',
            'prefix'       => 'STN',
            'include_year' => true,
            'padding'      => 4,
            'reset_yearly' => true,
            'next_number'  => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumnIfExists(['is_special_treatment', 'special_treatment_reason']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumnIfExists(['is_special_treatment', 'special_treatment_reason']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumnIfExists(['is_special_treatment', 'special_treatment_reason']);
        });

        DB::table('document_sequences')->where('type', 'STN')->delete();
    }
};
