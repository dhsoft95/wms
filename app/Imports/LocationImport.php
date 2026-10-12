<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;

class LocationImport extends BaseImport
{
    public function headers(): array
    {
        return ['warehouse_code', 'location_code', 'parent_code', 'type', 'max_pallets', 'max_weight_kg'];
    }

    public function templateRows(): array
    {
        return [
            ['warehouse_code' => 'DAR', 'location_code' => 'DAR-A-03-02-B', 'parent_code' => '', 'type' => 'bin', 'max_pallets' => '', 'max_weight_kg' => ''],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'warehouse_code' => 'Required. Must match an existing warehouse code.',
            'location_code'  => 'Required. Full hierarchical code (e.g. DAR-A-03-02-B). Parents created automatically.',
            'parent_code'    => 'Optional. Use if providing partial codes.',
            'type'           => 'Optional. bin, aisle, zone, quarantine, etc.',
            'max_pallets'    => 'Optional.',
            'max_weight_kg'  => 'Optional.',
        ];
    }

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];
        $whCode = $this->str($row, 'warehouse_code');
        if ($whCode === '') {
            $errors[] = $this->err('warehouse_code', "'warehouse_code' is required.");
        } elseif (! Warehouse::where('code', $whCode)->exists()) {
            $errors[] = $this->err('warehouse_code', "Warehouse '{$whCode}' not found.");
        }
        if ($this->str($row, 'location_code') === '') {
            $errors[] = $this->err('location_code', "'location_code' is required.");
        }
        return $errors;
    }

    protected function importRow(array $row): string
    {
        $warehouse = Warehouse::where('code', $this->str($row, 'warehouse_code'))->firstOrFail();
        $fullCode  = $this->str($row, 'location_code');
        $type      = $this->str($row, 'type');
        $locType   = $type !== '' ? LocationType::tryFrom($type) : null;

        // Build or find each ancestor segment
        $segments  = explode('-', $fullCode);
        $parentId  = null;
        $builtCode = '';

        foreach ($segments as $i => $segment) {
            $builtCode = $builtCode === '' ? $segment : "{$builtCode}-{$segment}";
            $isLeaf    = ($i === count($segments) - 1);

            $loc = Location::firstOrNew([
                'code'         => $builtCode,
                'warehouse_id' => $warehouse->id,
            ]);
            $isNew = ! $loc->exists;

            if ($isNew || ($isLeaf && $this->import->mode->value !== 'create_only')) {
                $loc->name         = $loc->name ?? $builtCode;
                $loc->parent_id    = $parentId;
                $loc->warehouse_id = $warehouse->id;
                $loc->type         = $isLeaf && $locType ? $locType : ($loc->type ?? LocationType::Bin);
                if ($isLeaf) {
                    $mp = $this->str($row, 'max_pallets');
                    $mw = $this->str($row, 'max_weight_kg');
                    $loc->max_pallets   = $mp !== '' ? (int) $mp : $loc->max_pallets;
                    $loc->max_weight_kg = $mw !== '' ? (float) $mw : $loc->max_weight_kg;
                }
                $loc->save();
            }

            $parentId = $loc->id;
        }

        $existing = Location::where('code', $fullCode)->where('warehouse_id', $warehouse->id)->first();
        return ($existing && ! $existing->wasRecentlyCreated) ? 'updated' : 'created';
    }
}
