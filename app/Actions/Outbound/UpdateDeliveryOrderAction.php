<?php

declare(strict_types=1);

namespace App\Actions\Outbound;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use Illuminate\Support\Facades\DB;

final class UpdateDeliveryOrderAction
{
    public function execute(DeliveryOrder $do, array $data): DeliveryOrder
    {
        if ($do->status !== DeliveryOrderStatus::Draft) {
            throw new \DomainException('Only draft delivery orders can be edited.');
        }

        return DB::transaction(function () use ($do, $data): DeliveryOrder {
            $do->update([
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'warehouse_id'         => $data['warehouse_id'],
                'department_id'        => $data['department_id'] ?: null,
                'consignee_partner_id' => $data['consignee_partner_id'] ?: null,
                'delivery_address'     => $data['delivery_address'] ?? null,
                'required_date'        => $data['required_date'],
                'priority'             => $data['priority'],
                'allocation_rule'      => $data['allocation_rule'],
                'remarks'              => $data['remarks'] ?? null,
            ]);

            // Delete existing lines and re-create for simplicity during draft edit
            $do->lines()->delete();

            foreach ($data['lines'] as $i => $line) {
                DeliveryOrderLine::create([
                    'delivery_order_id'  => $do->id,
                    'line_no'            => $i + 1,
                    'item_id'            => $line['item_id'],
                    'uom_id'             => $line['uom_id'],
                    'requested_qty'      => $line['qty'],
                    'requested_qty_base' => $line['qty'],
                    'notes'              => $line['notes'] ?? null,
                    'status'             => DoLineStatus::Pending,
                ]);
            }

            return $do->fresh();
        });
    }
}
