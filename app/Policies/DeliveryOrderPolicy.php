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

        if (! $do->status->isEditable()) {
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

        if (! $do->status->isEditable()) {
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

        if ($user->hasRole('system-admin')) {
            return true;
        }

        if ($do->department_id && $do->department) {
            return $do->department->head_user_id === $user->id;
        }

        return $user->hasRole('warehouse-manager');
    }

    public function returnOrder(User $user, DeliveryOrder $do): bool
    {
        if ($do->status !== DeliveryOrderStatus::Submitted) {
            return false;
        }

        if ($user->hasRole('system-admin')) {
            return true;
        }

        return $do->department?->head_user_id === $user->id;
    }

    public function forwardReturn(User $user, DeliveryOrder $do): bool
    {
        if ($do->status !== DeliveryOrderStatus::ReturnedForCorrection) {
            return false;
        }

        if ($user->hasRole('system-admin')) {
            return true;
        }

        return $do->department?->head_user_id === $user->id;
    }

    public function approveWarehouse(User $user, DeliveryOrder $do): bool
    {
        if (! $user->can('orders.approve.warehouse')) {
            return false;
        }

        // Managers/admins bypass the category check
        if ($user->hasAnyRole(['system-admin', 'warehouse-manager'])) {
            return true;
        }

        // Category users: ALL lines must be in their assigned categories for this warehouse
        // Use DB query to avoid global Eloquent scopes filtering out items/lines
        $lineCategoryIds = \DB::table('delivery_order_lines')
            ->join('items', 'delivery_order_lines.item_id', '=', 'items.id')
            ->where('delivery_order_lines.delivery_order_id', $do->id)
            ->pluck('items.category_id')
            ->filter()
            ->unique();

        if ($lineCategoryIds->isEmpty()) {
            return false;
        }

        $assignedCategoryIds = \App\Models\ItemCategoryUser::where('user_id', $user->id)
            ->where('warehouse_id', $do->warehouse_id)
            ->pluck('item_category_id');

        return $lineCategoryIds->diff($assignedCategoryIds)->isEmpty();
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
