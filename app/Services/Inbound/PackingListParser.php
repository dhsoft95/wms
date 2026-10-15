<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Models\Item;
use App\Models\ItemUom;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use Spatie\SimpleExcel\SimpleExcelReader;

class PackingListParser
{
    const FIELD_ALIASES = [
        'sku'         => ['sku', 'item code', 'item no', 'article no', 'part no', 'product code',
                          'item number', 'product no', 'code', 'material code', 'material no'],
        'description' => ['description', 'product name', 'item name', 'goods description',
                          'product description', 'desc', 'name', 'item description'],
        'qty'         => ['quantity', 'qty', 'units', 'pieces', 'pcs', 'count',
                          'amount', 'cartons', 'ctns', 'no of ctns', 'no. of ctns'],
        'uom'         => ['unit', 'uom', 'unit of measure', 'u/m', 'pack type',
                          'uom code', 'unit code', 'pack unit', 'uom/unit'],
        'batch'       => ['batch no', 'batch', 'lot no', 'lot', 'batch number',
                          'lot number', 'batch no.', 'lot no.'],
        'expiry'      => ['expiry date', 'expiry', 'best before', 'exp date', 'exp',
                          'use by', 'expiration date', 'expiration'],
        'weight'      => ['weight', 'net weight', 'gross weight', 'weight kg',
                          'nw', 'gw', 'weight (kg)', 'net wt', 'gross wt'],
        'vw'          => ['department', 'dept', 'virtual warehouse', 'vw',
                          'cost centre', 'cost center', 'department/vw', 'dept/vw'],
    ];

    /**
     * Read first $limit raw rows without header interpretation.
     * Each row is a zero-indexed array of cell values cast to strings.
     */
    public function readRawRows(string $path, int $limit = 20): array
    {
        $rows = [];
        try {
            $reader = SimpleExcelReader::create($path)->noHeaderRow();
            foreach ($reader->getRows() as $row) {
                $rows[] = array_values((array) $row);
                if (count($rows) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable) {
            // unreadable file
        }
        return $rows;
    }

    /**
     * Find the first row with ≥3 cells matching known field aliases.
     * Returns 0-indexed row number, defaulting to 0 if none found.
     */
    public function detectHeaderRow(array $rawRows): int
    {
        $aliases = array_merge(...array_values(self::FIELD_ALIASES));

        foreach ($rawRows as $idx => $row) {
            $matches = 0;
            foreach ($row as $cell) {
                if ($cell === null || $cell === '') {
                    continue;
                }
                $norm = strtolower(trim((string) $cell));
                if (in_array($norm, $aliases, true)) {
                    $matches++;
                }
            }
            if ($matches >= 3) {
                return $idx;
            }
        }

        return 0;
    }

    /**
     * Auto-map file column names to field keys using known aliases.
     * Returns ['column_name' => 'field_key'] where field_key is '' when unmatched.
     */
    public function autoMapColumns(array $columns): array
    {
        $mapping = [];
        foreach ($columns as $col) {
            $norm  = strtolower(trim((string) $col));
            $field = '';
            foreach (self::FIELD_ALIASES as $fieldKey => $aliases) {
                if (in_array($norm, $aliases, true)) {
                    $field = $fieldKey;
                    break;
                }
            }
            $mapping[(string) $col] = $field;
        }
        return $mapping;
    }

    /**
     * Parse all data rows after $headerRowIdx from file using the column mapping.
     * $mapping: ['file_col_name' => 'field_key'] ('' = skip column).
     * Returns array of ['field_key' => 'raw_value'].
     */
    public function parseDataRows(string $path, int $headerRowIdx, array $mapping): array
    {
        $allRaw    = $this->readRawRows($path, 10000);
        $headerRow = $allRaw[$headerRowIdx] ?? [];

        $results = [];
        for ($i = $headerRowIdx + 1; $i < count($allRaw); $i++) {
            $rawRow = $allRaw[$i];
            $parsed = [];
            foreach ($headerRow as $colIdx => $colName) {
                $fieldKey = $mapping[(string) $colName] ?? '';
                if ($fieldKey !== '') {
                    $parsed[$fieldKey] = trim((string) ($rawRow[$colIdx] ?? ''));
                }
            }
            // Skip fully empty rows
            if (empty(array_filter($parsed, fn ($v) => $v !== ''))) {
                continue;
            }
            $results[] = $parsed;
        }
        return $results;
    }

    /**
     * Resolve a single parsed row against DB records.
     * Returns a flat array of scalars safe for Livewire serialization.
     */
    public function resolveRow(array $row): array
    {
        $r = [
            'raw_sku'          => $row['sku'] ?? '',
            'raw_description'  => $row['description'] ?? '',
            'raw_qty'          => $row['qty'] ?? '',
            'raw_uom'          => $row['uom'] ?? '',
            'raw_batch'        => $row['batch'] ?? '',
            'raw_expiry'       => $row['expiry'] ?? '',
            'raw_weight'       => $row['weight'] ?? '',
            'raw_vw'           => $row['vw'] ?? '',
            'item_id'          => null,
            'item_sku'         => null,
            'item_name'        => null,
            'track_batch'      => false,
            'track_expiry'     => false,
            'uom_id'           => null,
            'uom_code'         => null,
            'base_uom_id'      => null,
            'base_uom_code'    => null,
            'qty_base'         => 0.0,
            'vw_id'            => null,
            'vw_name'          => null,
            'unknown_sku'      => false,
            'override_item_id' => null,
            'errors'           => [],
        ];

        // Resolve item by SKU, then barcode
        $sku = $r['raw_sku'];
        $item = null;
        if ($sku !== '') {
            $item = Item::where('sku', $sku)->first()
                ?? Item::whereHas('barcodes', fn ($q) => $q->where('barcode', $sku))->first();
            if ($item) {
                $r = $this->applyItem($r, $item);
            } else {
                $r['unknown_sku'] = true;
            }
        }

        // Resolve UoM
        $uomCode = $r['raw_uom'];
        $uom     = null;
        if ($uomCode !== '') {
            $uom = UnitOfMeasure::where('code', $uomCode)->first();
            if ($uom) {
                $r['uom_id']   = $uom->id;
                $r['uom_code'] = $uom->code;
            }
        }

        // Qty + conversion to base UoM
        $r = $this->resolveQty($r, $uom);

        // Resolve VW: from column → item default
        $r = $this->resolveVw($r);

        // Validate
        $r['errors'] = $this->validate($r);

        return $r;
    }

    /**
     * Re-resolve a row after the user picks an item override.
     */
    public function resolveWithOverride(array $row, int $itemId): array
    {
        $item = Item::with('baseUom')->find($itemId);
        if (!$item) {
            return $row;
        }

        $row = $this->applyItem($row, $item);
        $row['unknown_sku']      = false;
        $row['override_item_id'] = $item->id;

        // Re-resolve qty with existing UoM
        $uom = $row['uom_id'] ? UnitOfMeasure::find($row['uom_id']) : null;
        $row = $this->resolveQty($row, $uom);

        // Re-resolve VW if still missing
        $row = $this->resolveVw($row);

        $row['errors'] = $this->validate($row);

        return $row;
    }

    /** Map of field keys to display labels for the column-mapping step. */
    public static function fieldOptions(): array
    {
        return [
            ''            => '— Skip —',
            'sku'         => 'SKU',
            'description' => 'Description',
            'qty'         => 'Qty',
            'uom'         => 'UoM',
            'batch'       => 'Batch No',
            'expiry'      => 'Expiry',
            'weight'      => 'Weight (kg)',
            'vw'          => 'Department / VW',
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function applyItem(array $r, Item $item): array
    {
        $r['item_id']      = $item->id;
        $r['item_sku']     = $item->sku;
        $r['item_name']    = $item->name;
        $r['track_batch']  = (bool) $item->track_batch;
        $r['track_expiry'] = (bool) $item->track_expiry;
        $r['base_uom_id']  = $item->base_uom_id;
        // Resolve the base UoM code
        $baseUom = $item->baseUom ?? UnitOfMeasure::find($item->base_uom_id);
        $r['base_uom_code'] = $baseUom?->code;
        return $r;
    }

    private function resolveQty(array $r, ?UnitOfMeasure $uom): array
    {
        $qty = (float) ($r['raw_qty'] ?: 0);

        if ($r['item_id'] && $uom && $r['base_uom_id'] && $uom->id !== $r['base_uom_id']) {
            $conversion = ItemUom::where('item_id', $r['item_id'])
                ->where('uom_id', $uom->id)
                ->first();
            if ($conversion) {
                $qty = $qty * (float) $conversion->factor_to_base;
            }
        }

        // If no item-specific base UoM yet, use the UoM as the base
        if (!$r['base_uom_id'] && $uom) {
            $r['base_uom_id']   = $uom->id;
            $r['base_uom_code'] = $uom->code;
        }

        $r['qty_base'] = round($qty, 4);
        return $r;
    }

    private function resolveVw(array $r): array
    {
        if (!$r['vw_id']) {
            $vwCode = $r['raw_vw'];
            $vw     = null;
            if ($vwCode !== '') {
                $vw = VirtualWarehouse::where('code', $vwCode)->first()
                    ?? VirtualWarehouse::where('name', $vwCode)->first();
            }
            if (!$vw && $r['item_id']) {
                $vw = Item::find($r['item_id'])?->defaultVirtualWarehouse;
            }
            if ($vw) {
                $r['vw_id']   = $vw->id;
                $r['vw_name'] = $vw->name;
            }
        }
        return $r;
    }

    public function validate(array $r): array
    {
        $errors = [];

        if (($r['raw_qty'] ?? '') === '' || (float) ($r['raw_qty'] ?? 0) <= 0) {
            $errors[] = 'Qty required';
        }
        if (!$r['vw_id']) {
            $errors[] = 'VW not resolved';
        }
        if ($r['item_id'] && $r['track_batch'] && ($r['raw_batch'] ?? '') === '') {
            $errors[] = 'Batch required';
        }
        if ($r['item_id'] && $r['track_expiry'] && ($r['raw_expiry'] ?? '') === '') {
            $errors[] = 'Expiry required';
        }

        return $errors;
    }
}
