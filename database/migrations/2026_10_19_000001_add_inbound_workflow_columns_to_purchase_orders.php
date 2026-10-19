<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('virtual_warehouse_id')
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('consignee_partner_id')
                ->nullable()
                ->after('supplier_partner_id')
                ->constrained('partners')
                ->nullOnDelete();

            $table->string('currency', 3)->default('TZS')->after('consignee_partner_id');

            $table->foreignId('assigned_supervisor_id')
                ->nullable()
                ->after('requested_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_clerk_id')
                ->nullable()
                ->after('assigned_supervisor_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('acceptance_rejection_reason')->nullable()->after('rejection_reason');

            $table->timestamp('accepted_at')->nullable()->after('approved_at');
            $table->timestamp('assigned_supervisor_at')->nullable()->after('accepted_at');
            $table->timestamp('assigned_clerk_at')->nullable()->after('assigned_supervisor_at');
            $table->timestamp('putaway_validated_at')->nullable()->after('received_at');
            $table->timestamp('verified_at')->nullable()->after('putaway_validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['consignee_partner_id']);
            $table->dropForeign(['assigned_supervisor_id']);
            $table->dropForeign(['assigned_clerk_id']);
            $table->dropColumn([
                'department_id',
                'consignee_partner_id',
                'currency',
                'assigned_supervisor_id',
                'assigned_clerk_id',
                'acceptance_rejection_reason',
                'accepted_at',
                'assigned_supervisor_at',
                'assigned_clerk_at',
                'putaway_validated_at',
                'verified_at',
            ]);
        });
    }
};
