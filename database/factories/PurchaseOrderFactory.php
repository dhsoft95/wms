<?php

namespace Database\Factories;

use App\Enums\Inbound\PackageType;
use App\Enums\Inbound\PurchaseOrderSource;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    private static int $n = 1;

    public function definition(): array
    {
        return [
            'number'        => 'PO-' . date('Y') . '-' . str_pad((string) (self::$n++), 4, '0', STR_PAD_LEFT),
            'source'        => PurchaseOrderSource::Manual,
            'package_type'  => PackageType::LooseCargo,
            'status'        => PurchaseOrderStatus::Draft,
            'warehouse_id'  => Warehouse::factory(),
            'requested_by'  => User::factory(),
            'currency'      => 'TZS',
            'expected_date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'notes'         => null,
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state([
            'status'       => PurchaseOrderStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
    }

    public function approvedPendingAcceptance(): static
    {
        return $this->state([
            'status'       => PurchaseOrderStatus::ApprovedPendingAcceptance,
            'submitted_at' => now(),
            'approved_at'  => now(),
        ]);
    }

    public function acceptedPendingAssignment(): static
    {
        return $this->state([
            'status'       => PurchaseOrderStatus::AcceptedPendingAssignment,
            'submitted_at' => now(),
            'approved_at'  => now(),
            'accepted_at'  => now(),
        ]);
    }

    /** @deprecated Use pendingApproval() */
    public function submitted(): static
    {
        return $this->pendingApproval();
    }

    /** @deprecated Use approvedPendingAcceptance() */
    public function approved(): static
    {
        return $this->approvedPendingAcceptance();
    }
}
