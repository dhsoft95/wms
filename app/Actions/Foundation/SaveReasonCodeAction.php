<?php

namespace App\Actions\Foundation;

use App\Models\ReasonCode;

class SaveReasonCodeAction
{
    public static function create(array $data): ReasonCode
    {
        return ReasonCode::create([
            'code'       => $data['code'],
            'name'       => $data['name'],
            'applies_to' => $data['applies_to'],
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    public static function update(int $id, array $data): void
    {
        ReasonCode::findOrFail($id)->update([
            'code'       => $data['code'],
            'name'       => $data['name'],
            'applies_to' => $data['applies_to'],
            'is_active'  => $data['is_active'] ?? true,
        ]);
    }

    public static function delete(int $id): void
    {
        ReasonCode::findOrFail($id)->delete();
    }
}
