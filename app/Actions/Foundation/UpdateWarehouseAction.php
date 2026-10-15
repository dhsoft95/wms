<?php

namespace App\Actions\Foundation;

use App\Exceptions\InUseException;
use App\Models\Warehouse;

class UpdateWarehouseAction
{
    public static function execute(Warehouse $warehouse, array $data): void
    {
        $isDeactivating = isset($data['is_active']) && ! $data['is_active'] && $warehouse->is_active;

        if ($isDeactivating && $warehouse->isInUse()) {
            throw new InUseException('Warehouse', $warehouse->code);
        }

        $warehouse->update([
            'code'                                => strtoupper($data['code']),
            'name'                                => $data['name'],
            'address'                             => $data['address'] ?: null,
            'is_active'                           => $data['is_active'] ?? $warehouse->is_active,
            'default_receiving_dock_location_id'  => $data['default_receiving_dock_location_id'] ?? $warehouse->default_receiving_dock_location_id,
            'default_quarantine_location_id'      => $data['default_quarantine_location_id'] ?? $warehouse->default_quarantine_location_id,
            'default_staging_location_id'         => $data['default_staging_location_id'] ?? $warehouse->default_staging_location_id,
        ]);
    }
}
