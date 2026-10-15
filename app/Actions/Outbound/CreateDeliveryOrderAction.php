<?php

declare(strict_types=1);

namespace App\Actions\Outbound;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\User;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Support\Facades\DB;

final class CreateDeliveryOrderAction
{
    public function execute(array $data, User $by): DeliveryOrder
    {
        return DB::transaction(function () use ($data, $by): DeliveryOrder {
            $year   = now()->format('Y');
            $count  = DeliveryOrder::whereYear('created_at', $year)->count();
            $number = 'DO-' . $year . '-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

            $do = DeliveryOrder::create([
                'number'               => $number,
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'warehouse_id'         => $data['warehouse_id'],
                'department_id'        => $data['department_id'] ?: null,
                'consignee_partner_id' => $data['consignee_partner_id'] ?: null,
                'delivery_address'     => $data['delivery_address'] ?? null,
                'required_date'        => $data['required_date'],
                'priority'             => $data['priority'],
                'allocation_rule'      => $data['allocation_rule'],
                'remarks'              => $data['remarks'] ?? null,
                'requested_by'         => $by->id,
                'status'               => DeliveryOrderStatus::Draft,
            ]);

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

            if (! empty($data['auto_submit'])) {
                app(DeliveryOrderWorkflow::class)->submit($do, $by);
            }

            return $do;
        });
    }
}
