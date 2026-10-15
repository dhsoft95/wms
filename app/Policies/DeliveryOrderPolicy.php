<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
use App\Models\User;

class DeliveryOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.view');
    }

    public function view(User $user, DeliveryOrder $do): bool
    {
        if (! $user->can('orders.view')) {
            return false;
        }

        if ($user->canAccessAllVirtualWarehouses()) {
            return true;
        }

        $allowed = $user->accessibleVirtualWarehouseIds();

        return empty($allowed) || in_array($do->virtual_warehouse_id, $allowed, true);
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    public function update(User $user, DeliveryOrder $do): bool
    {
        if (! $user->can('orders.create')) {
            return false;
        }

        if ($do->status !== DeliveryOrderStatus::Draft) {
            return false;
        }

        if ($user->canAccessAllVirtualWarehouses()) {
            return true;
        }

        $allowed = $user->accessibleVirtualWarehouseIds();

        return empty($allowed) || in_array($do->virtual_warehouse_id, $allowed, true);
    }

    public function submit(User $user, DeliveryOrder $do): bool
    {
        if (! $user->can('orders.submit')) {
            return false;
        }

        if ($user->canAccessAllVirtualWarehouses()) {
            return true;
        }

        $allowed = $user->accessibleVirtualWarehouseIds();

        return empty($allowed) || in_array($do->virtual_warehouse_id, $allowed, true);
    }

    public function approveDepartment(User $user, DeliveryOrder $do): bool
    {
        if (! $user->can('orders.approve.department')) {
            return false;
        }

        // Must be the department head of the DO's department
        if ($do->department_id && $do->department) {
            return $do->department->head_user_id === $user->id;
        }

        return $user->hasRole('warehouse-manager') || $user->hasRole('system-admin');
    }

    public function approveWarehouse(User $user, DeliveryOrder $do): bool
    {
        return $user->can('orders.approve.warehouse');
    }

    public function allocate(User $user, DeliveryOrder $do): bool
    {
        return $user->can('orders.allocate');
    }

    public function cancel(User $user, DeliveryOrder $do): bool
    {
        return $user->can('orders.cancel');
    }

    public function uploadPod(User $user, DeliveryOrder $do): bool
    {
        return $user->can('pod.upload');
    }
}
