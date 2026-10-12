<?php

namespace App\Actions\Foundation;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class UpdateLocationAction
{
    public static function execute(Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data) {
            $warehouse  = Warehouse::findOrFail($data['warehouse_id']);
            $parent     = $data['parent_id'] ? Location::findOrFail($data['parent_id']) : null;
            $isPickable = LocationType::from($data['type'])->isPickable();
            $oldCode    = $location->code;
            $newCode    = static::buildCode($parent, $warehouse, $data['segment_code']);
            $newPath    = static::buildPath($parent);

            $location->update([
                'warehouse_id'  => $data['warehouse_id'],
                'parent_id'     => $parent?->id,
                'level'         => $data['level'],
                'segment_code'  => strtoupper($data['segment_code']),
                'code'          => $newCode,
                'type'          => $data['type'],
                'max_pallets'   => $data['max_pallets'] ?: null,
                'max_weight_kg' => $data['max_weight_kg'] ?: null,
                'barcode'       => $data['barcode'] ?: $newCode,
                'path'          => $newPath,
                'is_pickable'   => $isPickable,
            ]);

            // Rebuild all descendant codes when code changed
            if ($oldCode !== $newCode) {
                $descendants = $location->descendants();
                foreach ($descendants as $descendant) {
                    $descendant->update([
                        'code' => str_replace($oldCode, $newCode, $descendant->code, count: $n),
                    ]);
                }
            }

            return $location->refresh();
        });
    }

    public static function buildCode(?Location $parent, Warehouse $warehouse, string $segmentCode): string
    {
        $seg = strtoupper($segmentCode);
        return $parent ? "{$parent->code}-{$seg}" : "{$warehouse->code}-{$seg}";
    }

    public static function buildPath(?Location $parent): string
    {
        return $parent ? "{$parent->path}{$parent->id}/" : '/';
    }
}
