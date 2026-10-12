<?php

namespace App\Actions\Foundation;

use App\Models\DocumentSequence;

class SaveDocSequenceAction
{
    public static function execute(int $id, array $data): void
    {
        DocumentSequence::findOrFail($id)->update([
            'prefix'       => $data['prefix'] ?? null,
            'padding'      => (int) ($data['padding'] ?? 4),
            'include_year' => (bool) ($data['include_year'] ?? false),
            'reset_yearly' => (bool) ($data['reset_yearly'] ?? false),
        ]);
    }
}
