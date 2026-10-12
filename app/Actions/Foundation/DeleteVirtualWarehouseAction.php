<?php

namespace App\Actions\Foundation;

use App\Exceptions\InUseException;
use App\Models\VirtualWarehouse;

class DeleteVirtualWarehouseAction
{
    public static function execute(VirtualWarehouse $vw): void
    {
        if ($vw->isInUse()) {
            throw new InUseException('VirtualWarehouse', $vw->name);
        }

        $vw->delete();
    }
}
