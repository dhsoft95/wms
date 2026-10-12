<?php

namespace App\Actions\Foundation;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class BulkGenerateLocationsAction
{
    /**
     * @param  array<array{code: string, segment: string, exists: bool}> $previewList
     * @return array{created: int, skipped: int}
     */
    public static function execute(
        string $warehouseId,
        ?string $parentId,
        string $level,
        string $type,
        array $previewList
    ): array {
        return DB::transaction(function () use ($warehouseId, $parentId, $level, $type, $previewList) {
            $parent     = $parentId ? Location::find($parentId) : null;
            $isPickable = LocationType::from($type)->isPickable();
            $path       = $parent ? "{$parent->path}{$parent->id}/" : '/';
            $created    = 0;
            $skipped    = 0;

            foreach ($previewList as $entry) {
                if ($entry['exists']) {
                    $skipped++;
                    continue;
                }

                Location::create([
                    'warehouse_id'  => $warehouseId,
                    'parent_id'     => $parent?->id,
                    'level'         => $level,
                    'segment_code'  => $entry['segment'],
                    'code'          => $entry['code'],
                    'type'          => $type,
                    'status'        => 'active',
                    'path'          => $path,
                    'is_pickable'   => $isPickable,
                    'barcode'       => $entry['code'],
                ]);

                $created++;
            }

            return compact('created', 'skipped');
        });
    }
}
