<?php

declare(strict_types=1);

namespace App\Imports;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Models\DataImport;
use App\Models\Inventory\SerialNumber;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Inventory\Batch;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockLineValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

class OpeningStockImport extends BaseImport
{
    private const CHUNK = 500;

    public function headers(): array
    {
        return ['sku', 'location_code', 'vw_code', 'qty', 'uom', 'batch_no', 'expiry_date', 'status'];
    }

    public function templateRows(): array
    {
        return [
            ['sku' => 'RTR-001', 'location_code' => 'DAR-A-01-01-A', 'vw_code' => 'IT-VW', 'qty' => '10', 'uom' => 'PCS', 'batch_no' => '', 'expiry_date' => '', 'status' => 'available'],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'sku'           => 'Required. Must match an existing item SKU.',
            'location_code' => 'Required. Must match an existing location code.',
            'vw_code'       => 'Required. Must match an existing virtual warehouse code.',
            'qty'           => 'Required. Positive number.',
            'uom'           => 'Required. Must be a valid UoM code for this item.',
            'batch_no'      => 'Optional. Required for batch-tracked items.',
            'expiry_date'   => 'Optional. Format YYYY-MM-DD. Required for expiry-tracked items.',
            'status'        => 'Required. "available" or "on_hold".',
        ];
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];

        // ── Existence checks (before model resolution) ──────────────────────
        $sku = $this->str($row, 'sku');
        if ($sku === '') {
            return [$this->err('sku', "'sku' is required.")];
        }
        $item = Item::where('sku', $sku)->first();
        if (! $item) {
            return [$this->err('sku', "Item '{$sku}' not found.")];
        }

        $locationCode = $this->str($row, 'location_code');
        if ($locationCode === '') {
            $errors[] = $this->err('location_code', "'location_code' is required.");
        }

        $vwCode = $this->str($row, 'vw_code');
        if ($vwCode === '') {
            $errors[] = $this->err('vw_code', "'vw_code' is required.");
        }

        $status = $this->str($row, 'status');
        if (! in_array($status, ['available', 'on_hold'], true)) {
            $errors[] = $this->err('status', "'status' must be 'available' or 'on_hold'.");
        }

        $location = $locationCode !== '' ? Location::where('code', $locationCode)->first() : null;
        if ($locationCode !== '' && ! $location) {
            $errors[] = $this->err('location_code', "Location '{$locationCode}' not found.");
        }

        $vw = $vwCode !== '' ? VirtualWarehouse::where('code', $vwCode)->first() : null;
        if ($vwCode !== '' && ! $vw) {
            $errors[] = $this->err('vw_code', "Virtual warehouse '{$vwCode}' not found.");
        }

        if (! empty($errors)) {
            return $errors;
        }

        // ── Shared rule checks (via StockLineValidator) ──────────────────────
        $batchNo    = $this->str($row, 'batch_no') ?: null;
        $expiryDate = $this->str($row, 'expiry_date') ?: null;
        $qty        = $this->str($row, 'qty');

        $existingBatch = $batchNo !== null
            ? Batch::where('item_id', $item->id)->where('batch_no', $batchNo)->first()
            : null;

        foreach (StockLineValidator::check($item, $location, $vw, $existingBatch, $batchNo, $expiryDate, $qty) as $e) {
            $errors[] = $this->err($e['column'], $e['message']);
        }

        return $errors;
    }

    protected function importRow(array $row): string
    {
        // Used only during full import via BaseImport::import(); we override below for chunking
        return 'created';
    }

    protected function computeSummary(int $totalRows, int $validRows, int $errorRows): array
    {
        $path     = \Illuminate\Support\Facades\Storage::disk('local')->path($this->import->stored_path);
        $totalQty = '0';

        try {
            foreach (SimpleExcelReader::create($path)->getRows() as $row) {
                $qty = trim((string) ($row['qty'] ?? ''));
                if (is_numeric($qty)) {
                    $totalQty = bcadd($totalQty, $qty, 4);
                }
            }
        } catch (\Throwable) {
            // file unreadable — leave qty as 0
        }

        $serialCount = 0;
        try {
            $serialRows  = SimpleExcelReader::create($path)->useSheetIndex(1)->getRows()->all();
            $serialCount = count($serialRows);
        } catch (\Throwable) {
            // no serial sheet
        }

        return [
            'lines'         => $totalRows,
            'total_qty'     => rtrim(rtrim($totalQty, '0'), '.'),
            'serial_count'  => $serialCount,
            'transactions'  => $validRows > 0 ? (int) ceil($validRows / self::CHUNK) : 0,
            'errors'        => $errorRows,
        ];
    }

    // ─── Chunked import (overrides BaseImport::import) ────────────────────────

    public function import(): void
    {
        $path    = \Illuminate\Support\Facades\Storage::disk('local')->path($this->import->stored_path);
        $rows    = SimpleExcelReader::create($path)->getRows()->collect()->all();
        $serials = $this->loadSerialsSheet($path);
        $errors  = [];
        $created = 0;

        // Re-validate before writing
        foreach ($rows as $i => $row) {
            $rowErrors = $this->validateRow($i + 1, $row);
            foreach ($rowErrors as $e) {
                $errors[] = array_merge(['row' => $i + 1], $e);
            }
        }

        // Validate serial sheet counts match stock sheet
        [$serialErrors, $serialsByKey] = $this->validateSerialSheet($rows, $serials);
        $errors = array_merge($errors, $serialErrors);

        if (! empty($errors)) {
            $this->import->update(['status' => 'failed', 'errors' => $errors]);
            return;
        }

        // Chunk and post
        $ledger = app(StockLedger::class);
        $chunks = array_chunk($rows, self::CHUNK);

        foreach ($chunks as $chunkIndex => $chunk) {
            $idempotencyKey = "import:{$this->import->id}:chunk:{$chunkIndex}";

            $stockLines = [];
            foreach ($chunk as $row) {
                $stockLines[] = $this->buildLine($row, $serialsByKey);
            }

            $tx = $ledger->openingBalance($stockLines, "Import #{$this->import->id} chunk {$chunkIndex}", $idempotencyKey);
            $created += count($chunk);
        }

        $this->import->update([
            'created_count' => $created,
            'status'        => 'completed',
            'errors'        => [],
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildLine(array $row, array $serialsByKey): StockLine
    {
        $item     = Item::where('sku', $this->str($row, 'sku'))->firstOrFail();
        $location = Location::where('code', $this->str($row, 'location_code'))->firstOrFail();
        $vw       = VirtualWarehouse::where('code', $this->str($row, 'vw_code'))->firstOrFail();
        $uom      = UnitOfMeasure::where('code', $this->str($row, 'uom'))->firstOrFail();
        $status   = StockStatus::from($this->str($row, 'status') === 'on_hold' ? 'on_hold' : 'available');
        $expiry   = $this->str($row, 'expiry_date') !== '' ? Carbon::parse($this->str($row, 'expiry_date')) : null;

        $key     = "{$item->sku}|{$this->str($row, 'location_code')}|{$this->str($row, 'vw_code')}|{$this->str($row, 'batch_no')}";
        $serials = $serialsByKey[$key] ?? [];

        return new StockLine(
            itemId: $item->id,
            locationId: $location->id,
            virtualWarehouseId: $vw->id,
            stockStatus: $status,
            enteredQty: $this->str($row, 'qty'),
            enteredUomId: $uom->id,
            batchNo: $this->str($row, 'batch_no') ?: null,
            expiryDate: $expiry,
            serials: $serials,
        );
    }

    /** Read the optional Serials sheet (second sheet). Returns array keyed by sku|location|vw|batch */
    private function loadSerialsSheet(string $path): array
    {
        try {
            $serialRows = SimpleExcelReader::create($path)
                ->useSheetIndex(1)
                ->getRows()
                ->all();
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($serialRows as $row) {
            $key          = "{$this->str($row, 'sku')}|{$this->str($row, 'location_code')}|{$this->str($row, 'vw_code')}|{$this->str($row, 'batch_no')}";
            $map[$key][]  = $this->str($row, 'serial_no');
        }

        return $map;
    }

    /** Validate serial counts and check for already-in-stock conflicts */
    private function validateSerialSheet(array $rows, array $serialsByKey): array
    {
        $errors     = [];
        $allSerials = [];

        foreach ($rows as $i => $row) {
            $item = Item::where('sku', $this->str($row, 'sku'))->first();
            if (! $item || ! $item->track_serial) {
                continue;
            }

            $key         = "{$this->str($row, 'sku')}|{$this->str($row, 'location_code')}|{$this->str($row, 'vw_code')}|{$this->str($row, 'batch_no')}";
            $serialCount = count($serialsByKey[$key] ?? []);
            $qty         = (int) round((float) $this->str($row, 'qty'));

            if ($serialCount !== $qty) {
                $errors[] = [
                    'row'     => $i + 1,
                    'column'  => 'serials',
                    'message' => "Item '{$this->str($row, 'sku')}' requires {$qty} serial(s) but found {$serialCount} in the Serials sheet.",
                ];
                continue;
            }

            foreach (($serialsByKey[$key] ?? []) as $sn) {
                $normalized = SerialNumber::normalize((string) $sn);
                if ($normalized === '') {
                    $errors[] = [
                        'row'     => $i + 1,
                        'column'  => 'serials',
                        'message' => "Empty serial number in Serials sheet for item '{$this->str($row, 'sku')}'.",
                    ];
                    continue;
                }

                // Duplicate within this import file
                $fileKey = "{$item->id}:{$normalized}";
                if (isset($allSerials[$fileKey])) {
                    $errors[] = [
                        'row'     => $i + 1,
                        'column'  => 'serials',
                        'message' => "Duplicate serial '{$normalized}' for item '{$item->sku}' within this import file.",
                    ];
                    continue;
                }
                $allSerials[$fileKey] = true;

                // Already in stock in the database
                $existing = SerialNumber::where('item_id', $item->id)
                    ->where('serial_no', $normalized)
                    ->where('status', SerialStatus::InStock)
                    ->exists();

                if ($existing) {
                    $errors[] = [
                        'row'     => $i + 1,
                        'column'  => 'serials',
                        'message' => "Serial '{$normalized}' for item '{$item->sku}' is already in stock.",
                    ];
                }
            }
        }

        return [$errors, $serialsByKey];
    }
}
