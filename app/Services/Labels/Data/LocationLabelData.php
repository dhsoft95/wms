<?php

declare(strict_types=1);

namespace App\Services\Labels\Data;

use App\Models\Location;
use App\Services\Foundation\SettingsService;

final class LocationLabelData
{
    public static function build(Location $location): array
    {
        return [
            'company_name'   => SettingsService::get('company.name', config('app.name')),
            'location_code'  => $location->barcode ?: $location->code,
            'location_level' => $location->level?->label() ?? '',
            'location_type'  => $location->type?->value ?? '',
            'warehouse_name' => $location->warehouse?->name ?? '',
            'barcode'        => $location->barcode ?: $location->code,
        ];
    }
}
