<?php

namespace App\Actions\Foundation;

use App\Exceptions\InUseException;
use App\Models\Warehouse;

class ToggleWarehouseActiveAction
{
    public static function deactivate(Warehouse $warehouse): void
    {
        if ($warehouse->isInUse()) {
            throw new InUseException('Warehouse', $warehouse->code);
        }

        $warehouse->update(['is_active' => false]);
    }

    public static function activate(Warehouse $warehouse): void
    {
        $warehouse->update(['is_active' => true]);
    }
}
