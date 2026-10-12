<?php

namespace App\Actions\Foundation;

use App\Models\Warehouse;

class CreateWarehouseAction
{
    public static function execute(array $data): Warehouse
    {
        return Warehouse::create([
            'code'      => strtoupper($data['code']),
            'name'      => $data['name'],
            'address'   => $data['address'] ?: null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
