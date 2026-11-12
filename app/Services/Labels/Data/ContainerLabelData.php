<?php

declare(strict_types=1);

namespace App\Services\Labels\Data;

use App\Models\Container;
use App\Services\Foundation\SettingsService;

final class ContainerLabelData
{
    public static function build(Container $container): array
    {
        $container->loadMissing(['warehouse']);

        return [
            'company_name'  => SettingsService::get('company.name', config('app.name')),
            'container_no'  => $container->number ?? '',
            'iso_number'    => $container->iso_number ?? '',
            'warehouse_name'=> $container->warehouse?->name ?? '',
            'status'        => $container->status?->value ?? '',
        ];
    }
}
