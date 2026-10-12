<?php

namespace App\Actions\Foundation;

use App\Exceptions\InUseException;
use App\Models\UnitOfMeasure;

class SaveUomAction
{
    public static function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create([
            'code'      => strtoupper($data['code']),
            'name'      => $data['name'],
            'type'      => $data['type'],
            'is_active' => true,
        ]);
    }

    public static function update(int $id, array $data): void
    {
        UnitOfMeasure::findOrFail($id)->update([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'type' => $data['type'],
        ]);
    }

    public static function deactivate(int $id): void
    {
        $uom = UnitOfMeasure::findOrFail($id);

        if ($uom->isInUse()) {
            throw new InUseException('UnitOfMeasure', $uom->code);
        }

        $uom->update(['is_active' => false]);
    }

    public static function activate(int $id): void
    {
        UnitOfMeasure::findOrFail($id)->update(['is_active' => true]);
    }

    public static function delete(int $id): void
    {
        $uom = UnitOfMeasure::findOrFail($id);

        if ($uom->isInUse()) {
            throw new InUseException('UnitOfMeasure', $uom->code);
        }

        $uom->delete();
    }
}
