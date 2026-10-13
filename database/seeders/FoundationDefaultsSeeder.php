<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\ReasonCode;
use Illuminate\Database\Seeder;

class FoundationDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDocumentSequences();
        $this->seedReasonCodes();
    }

    private function seedDocumentSequences(): void
    {
        $sequences = [
            ['type' => 'GRN', 'prefix' => 'GRN', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
            ['type' => 'DO',  'prefix' => 'DO',  'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
            ['type' => 'ADJ', 'prefix' => 'ADJ', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
            ['type' => 'TRF', 'prefix' => 'TRF', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
            ['type' => 'CNT', 'prefix' => 'CNT', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
            ['type' => 'LPN', 'prefix' => 'LPN', 'include_year' => false, 'padding' => 6, 'reset_yearly' => false],
            ['type' => 'STK', 'prefix' => 'STK', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
        ];

        foreach ($sequences as $seq) {
            DocumentSequence::firstOrCreate(
                ['type' => $seq['type']],
                array_merge($seq, ['next_number' => 1])
            );
        }

        // CTR: format CTR-2026-0001 — year included, resets yearly
        DocumentSequence::updateOrCreate(
            ['type' => 'CTR'],
            ['prefix' => 'CTR', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true, 'next_number' => 1]
        );
    }

    private function seedReasonCodes(): void
    {
        $codes = [
            ['code' => 'VAR-DMGD',  'name' => 'Goods Damaged in Transit',     'applies_to' => 'variance'],
            ['code' => 'VAR-SHORT', 'name' => 'Goods Short on Delivery',      'applies_to' => 'variance'],
            ['code' => 'VAR-EXCS',  'name' => 'Goods Received in Excess',     'applies_to' => 'variance'],
            ['code' => 'ADJ-LOSS',  'name' => 'Stock Loss / Shrinkage',        'applies_to' => 'adjustment'],
            ['code' => 'ADJ-FOUND', 'name' => 'Found / Recovered Stock',       'applies_to' => 'adjustment'],
            ['code' => 'ADJ-EXPRY', 'name' => 'Expired Stock Write-Off',       'applies_to' => 'adjustment'],
            ['code' => 'ADJ-COUNT', 'name' => 'Count Correction',              'applies_to' => 'adjustment'],
            ['code' => 'SP-SYSTM',  'name' => 'System Stock Error',            'applies_to' => 'short_pick'],
            ['code' => 'SP-MISPL',  'name' => 'Goods Mislaid in Warehouse',   'applies_to' => 'short_pick'],
            ['code' => 'RPT-DMGD',  'name' => 'Original Label Damaged',        'applies_to' => 'reprint'],
            ['code' => 'RPT-LOST',  'name' => 'Label Lost or Missing',         'applies_to' => 'reprint'],
            ['code' => 'HLD-QUAL',  'name' => 'Quality Inspection Required',   'applies_to' => 'hold'],
            ['code' => 'HLD-DISP',  'name' => 'Ownership Dispute',             'applies_to' => 'hold'],
            ['code' => 'SEAL-BRKN', 'name' => 'Seal Broken on Arrival',        'applies_to' => 'seal_mismatch'],
            ['code' => 'SEAL-WMRK', 'name' => 'Seal Number Does Not Match BL', 'applies_to' => 'seal_mismatch'],
        ];

        foreach ($codes as $code) {
            ReasonCode::firstOrCreate(
                ['code' => $code['code']],
                array_merge($code, ['is_active' => true])
            );
        }
    }
}
