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
        $this->seedUnitOfMeasures();
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
            ['type' => 'MOV', 'prefix' => 'MOV', 'include_year' => true,  'padding' => 4, 'reset_yearly' => true],
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

        // Outbound sequences
        foreach ([
            ['type' => 'PCK', 'prefix' => 'PCK', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'DSP', 'prefix' => 'DSP', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'GP',  'prefix' => 'GP',  'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
        ] as $seq) {
            DocumentSequence::updateOrCreate(
                ['type' => $seq['type']],
                ['prefix' => $seq['prefix'], 'include_year' => $seq['include_year'], 'padding' => $seq['padding'], 'reset_yearly' => $seq['reset_yearly']]
            );
        }

        // Phase A — PO, MRN, RCN, DN, ODN
        foreach ([
            ['type' => 'PO',  'prefix' => 'PO',  'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'MRN', 'prefix' => 'MRN', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'RCN', 'prefix' => 'RCN', 'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'DN',  'prefix' => 'DN',  'include_year' => true, 'padding' => 4, 'reset_yearly' => true],
            ['type' => 'ODN', 'prefix' => 'ODN', 'include_year' => true, 'padding' => 6, 'reset_yearly' => true],
        ] as $seq) {
            DocumentSequence::updateOrCreate(
                ['type' => $seq['type']],
                ['prefix' => $seq['prefix'], 'include_year' => $seq['include_year'], 'padding' => $seq['padding'], 'reset_yearly' => $seq['reset_yearly']]
            );
        }
    }

    private function seedUnitOfMeasures(): void
    {
        $uoms = [
            ['code' => 'EA',   'name' => 'Each',         'type' => 'count'],
            ['code' => 'PCS',  'name' => 'Pieces',       'type' => 'count'],
            ['code' => 'CTN',  'name' => 'Carton',       'type' => 'count'],
            ['code' => 'BOX',  'name' => 'Box',          'type' => 'count'],
            ['code' => 'PLT',  'name' => 'Pallet',       'type' => 'count'],
            ['code' => 'KG',   'name' => 'Kilogram',     'type' => 'weight'],
            ['code' => 'G',    'name' => 'Gram',         'type' => 'weight'],
            ['code' => 'TON',  'name' => 'Metric Tonne', 'type' => 'weight'],
            ['code' => 'L',    'name' => 'Litre',        'type' => 'volume'],
            ['code' => 'ML',   'name' => 'Millilitre',   'type' => 'volume'],
            ['code' => 'M',    'name' => 'Metre',        'type' => 'length'],
            ['code' => 'CM',   'name' => 'Centimetre',   'type' => 'length'],
            ['code' => 'ROLL', 'name' => 'Roll',         'type' => 'count'],
            ['code' => 'SET',  'name' => 'Set',          'type' => 'count'],
            ['code' => 'PAIR', 'name' => 'Pair',         'type' => 'count'],
            ['code' => 'DRUM', 'name' => 'Drum',         'type' => 'count'],
            ['code' => 'BAG',  'name' => 'Bag',          'type' => 'count'],
            ['code' => 'PACK', 'name' => 'Pack',         'type' => 'count'],
            ['code' => 'CAN',  'name' => 'Can',          'type' => 'count'],
            ['code' => 'BTL',  'name' => 'Bottle',       'type' => 'count'],
            ['code' => 'DOZ',  'name' => 'Dozen',        'type' => 'count'],
        ];

        foreach ($uoms as $uom) {
            \App\Models\UnitOfMeasure::firstOrCreate(
                ['code' => $uom['code']],
                array_merge($uom, ['is_active' => true])
            );
        }
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
