<?php

namespace App\Services\Foundation;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    /**
     * Generate the next sequential number for a document type.
     * Uses a pessimistic row lock to prevent duplicates under concurrent requests.
     *
     * Format examples: GRN-2026-0001, DO-0023 (if include_year=false)
     */
    public static function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $seq = DocumentSequence::where('type', $type)
                ->lockForUpdate()
                ->firstOrFail();

            $number = $seq->next_number;

            // Yearly reset: if reset_yearly is on and we've rolled into a new year
            $currentYear = (int) now()->year;
            if ($seq->reset_yearly && $seq->last_reset_year && $seq->last_reset_year < $currentYear) {
                $number = 1;
                $seq->last_reset_year = $currentYear;
            } elseif ($seq->reset_yearly && ! $seq->last_reset_year) {
                $seq->last_reset_year = $currentYear;
            }

            $seq->next_number = $number + 1;
            $seq->saveQuietly(); // skip activity log for sequence increments

            $padded = str_pad((string) $number, $seq->padding, '0', STR_PAD_LEFT);

            if ($seq->include_year) {
                return "{$seq->prefix}-{$currentYear}-{$padded}";
            }

            return "{$seq->prefix}-{$padded}";
        });
    }
}
