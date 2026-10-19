<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AdjustmentStatus;
use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('adjustments.view');
    }

    public function view(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('adjustments.create');
    }

    public function submit(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.create')
            && $adj->status === AdjustmentStatus::Draft
            && $adj->created_by === $user->id;
    }

    public function approve(User $user, StockAdjustment $adj): bool
    {
        if (! $user->can('adjustments.approve') || $adj->status !== AdjustmentStatus::Submitted) {
            return false;
        }

        // Managers/admins bypass the category check
        if ($user->hasAnyRole(['system-admin', 'warehouse-manager'])) {
            return true;
        }

        // Category users: every line's (item category, location warehouse) must be in their assignments
        // Use DB query to avoid global Eloquent scopes filtering out items
        $lines = \DB::table('stock_adjustment_lines')
            ->join('items', 'stock_adjustment_lines.item_id', '=', 'items.id')
            ->join('locations', 'stock_adjustment_lines.location_id', '=', 'locations.id')
            ->where('stock_adjustment_lines.stock_adjustment_id', $adj->id)
            ->select('items.category_id', 'locations.warehouse_id')
            ->get();

        foreach ($lines as $line) {
            if (! $line->warehouse_id || ! $line->category_id) {
                return false;
            }

            $hasAssignment = \App\Models\ItemCategoryUser::where('user_id', $user->id)
                ->where('warehouse_id', $line->warehouse_id)
                ->where('item_category_id', $line->category_id)
                ->exists();

            if (! $hasAssignment) {
                return false;
            }
        }

        return true;
    }

    public function reject(User $user, StockAdjustment $adj): bool
    {
        return $this->approve($user, $adj);
    }
}
