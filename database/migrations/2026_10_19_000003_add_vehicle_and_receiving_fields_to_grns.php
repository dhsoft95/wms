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
            // Vehicle / transport — moved here from purchase_orders
            $table->string('vehicle_type')->nullable()->after('receiving_dock_location_id');
            $table->string('vehicle_plate')->nullable()->after('vehicle_type');
            $table->string('driver_name')->nullable()->after('vehicle_plate');
            $table->string('driver_phone')->nullable()->after('driver_name');
            $table->string('driver_licence')->nullable()->after('driver_phone');
            $table->string('transporter_name')->nullable()->after('driver_licence');

            // Container seal check (recorded at receiving time, not at PO time)
            $table->string('seal_check_result', 20)->nullable()->after('transporter_name'); // match / mismatch
            $table->text('seal_mismatch_reason')->nullable()->after('seal_check_result');
            $table->foreignId('seal_mismatch_acknowledged_by')
                ->nullable()
                ->after('seal_mismatch_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('seal_mismatch_acknowledged_at')->nullable()->after('seal_mismatch_acknowledged_by');

            // Delivery metadata
            $table->string('delivery_type', 20)->nullable()->after('seal_mismatch_acknowledged_at'); // partial / full
            $table->timestamp('arrival_datetime')->nullable()->after('delivery_type');
            $table->unsignedSmallInteger('grn_delivery_no')->nullable()->after('arrival_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('grns', function (Blueprint $table) {
            $table->dropForeign(['seal_mismatch_acknowledged_by']);
            $table->dropColumn([
                'vehicle_type', 'vehicle_plate', 'driver_name', 'driver_phone',
                'driver_licence', 'transporter_name',
                'seal_check_result', 'seal_mismatch_reason',
                'seal_mismatch_acknowledged_by', 'seal_mismatch_acknowledged_at',
                'delivery_type', 'arrival_datetime', 'grn_delivery_no',
            ]);
        });
    }
};
