<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Inventory\StockKey;
use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkLineImportService
{
    private const MAX_ROWS = 1000;

    /**
     * Process an uploaded file (XLSX or CSV) and return resolved rows + errors.
     *
     * @param  string  $path     Absolute path to the uploaded file
     * @param  array   $fields   lineFields() definition array
     * @param  array   $context  Optional: virtual_warehouse_id, check_available, location_key, adj_mode
     * @return array{valid: array, errors: array, total: int, truncated: bool}
     */
    public function process(string $path, array $fields, array $context = []): array
    {
        $rawRows   = [];
        $errors    = [];
        $valid     = [];
        $total     = 0;
        $truncated = false;

        // Read file rows using spatie/simple-excel (first row = headers)
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path)->getRows();

        foreach ($reader as $rowData) {
            $total++;
            if ($total > self::MAX_ROWS) {
                $truncated = true;
                continue; // count but don't process
            }
            $rawRows[] = $rowData;
        }

        // ── Pass 1: resolve codes to IDs, validate types ──────────────────────
        $resolvedAll = [];  // index → ['resolved' => [], 'row' => int, 'failed' => bool]

        foreach ($rawRows as $rowNum => $rowData) {
            $humanRow  = $rowNum + 2; // 1-based + header
            $resolved  = [];
            $rowFailed = false;

            foreach ($fields as $field) {
                $col  = $field['column'];
                $key  = $field['key'];
                $type = $field['type'];
                $req  = $field['required'];

                $raw = isset($rowData[$col]) ? trim((string) $rowData[$col]) : '';

                if ($raw === '') {
                    if ($req) {
                        $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => 'Required field is empty.'];
                        $rowFailed = true;
                    }
                    $resolved[$key] = null;
                    continue;
                }

                switch ($type) {
                    case 'sku':
                        $item = Item::where('sku', $raw)->where('is_active', true)->first();
                        if (! $item) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "SKU '{$raw}' not found or inactive."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        } else {
                            $resolved[$key] = $item->id;
                        }
                        break;

                    case 'uom':
                        $uom = UnitOfMeasure::where('code', $raw)->first();
                        if (! $uom) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "UoM code '{$raw}' not found."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        } else {
                            $resolved[$key] = $uom->id;
                        }
                        break;

                    case 'bin':
                        $loc = Location::active()->bin()->where('code', $raw)->first();
                        if (! $loc) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "Bin '{$raw}' not found or inactive."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        } else {
                            $resolved[$key] = $loc->id;
                        }
                        break;

                    case 'reason_code':
                        $rc = ReasonCode::where('code', $raw)->where('is_active', true)->first();
                        if (! $rc) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "Reason code '{$raw}' not found or inactive."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        } else {
                            $resolved[$key] = $rc->id;
                        }
                        break;

                    case 'decimal':
                        if (! is_numeric($raw)) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "'{$raw}' is not a valid number."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        } else {
                            $resolved[$key] = $raw;
                        }
                        break;

                    case 'date':
                        try {
                            $resolved[$key] = Carbon::createFromFormat('Y-m-d', $raw)->format('Y-m-d');
                        } catch (\Exception) {
                            $errors[] = ['row' => $humanRow, 'column' => $col, 'message' => "Date '{$raw}' must be in YYYY-MM-DD format."];
                            $rowFailed = true;
                            $resolved[$key] = null;
                        }
                        break;

                    case 'text':
                        $resolved[$key] = $raw;
                        break;

                    case 'serials':
                        // Keep raw string; serial count check happens in pass 2
                        $resolved[$key] = $raw;
                        break;

                    default:
                        $resolved[$key] = $raw;
                }
            }

            $resolvedAll[$rowNum] = [
                'resolved' => $resolved,
                'row'      => $humanRow,
                'failed'   => $rowFailed,
            ];
        }

        // ── Pass 2: serial checks, stock checks ───────────────────────────────
        // Track which rows are rejected in this pass
        $rejectedInPass2 = [];

        foreach ($resolvedAll as $rowNum => $data) {
            if ($data['failed']) {
                continue; // already has errors from pass 1
            }

            $resolved = $data['resolved'];
            $humanRow = $data['row'];
            $rowFailed = false;

            // Serial count check
            if (isset($resolved['item_id']) && isset($resolved['serials'])) {
                $item = Item::find($resolved['item_id']);
                if ($item && $item->track_serial) {
                    $serialStr   = $resolved['serials'] ?? '';
                    $serialArray = $this->parseSerials($serialStr);
                    $expectedQty = isset($resolved['qty']) ? (int) round((float) $resolved['qty']) : 0;
                    if (count($serialArray) !== $expectedQty) {
                        $errors[] = [
                            'row'     => $humanRow,
                            'column'  => 'Serials',
                            'message' => "Expected {$expectedQty} serial(s), got " . count($serialArray) . '.',
                        ];
                        $rowFailed = true;
                    } else {
                        $resolved['serials'] = $serialArray;
                    }
                } else {
                    $resolved['serials'] = $this->parseSerials($resolved['serials'] ?? '');
                }
            } elseif (isset($resolved['serials'])) {
                $resolved['serials'] = $this->parseSerials($resolved['serials'] ?? '');
            }

            if ($rowFailed) {
                $rejectedInPass2[$rowNum] = true;
                $resolvedAll[$rowNum]['failed'] = true;
                continue;
            }

            // Stock availability check
            if (! empty($context['check_available']) && ! empty($context['virtual_warehouse_id'])) {
                $qty       = (float) ($resolved['qty'] ?? 0);
                $adjMode   = ! empty($context['adj_mode']);
                $shouldCheck = ! $adjMode || $qty < 0;

                if ($shouldCheck) {
                    $locationKey = $context['location_key'] ?? 'location_id';
                    $locationId  = $resolved[$locationKey] ?? null;
                    $itemId      = $resolved['item_id'] ?? null;

                    if ($locationId && $itemId) {
                        $stockKey = new StockKey(
                            itemId: (int) $itemId,
                            batchId: null,
                            lpnId: null,
                            locationId: (int) $locationId,
                            virtualWarehouseId: (int) $context['virtual_warehouse_id'],
                            stockStatus: StockStatus::Available,
                        );

                        $balance   = StockBalance::where('key_hash', $stockKey->hash())->first();
                        $available = $balance ? $balance->qtyAvailable() : '0.0000';
                        $absQty    = abs($qty);

                        if (bccomp((string) $absQty, $available, 4) > 0) {
                            $errors[] = [
                                'row'     => $humanRow,
                                'column'  => 'Quantity',
                                'message' => "Insufficient stock. Available: {$available}.",
                            ];
                            $rejectedInPass2[$rowNum] = true;
                            $resolvedAll[$rowNum]['failed'] = true;
                            continue;
                        }
                    }
                }
            }

            // Update resolved data (serial arrays, etc.)
            $resolvedAll[$rowNum]['resolved'] = $resolved;
        }

        // ── Pass 3: duplicate detection (only among non-failed rows) ──────────
        $dupeIndex = [];

        foreach ($resolvedAll as $rowNum => $data) {
            if ($data['failed']) {
                continue;
            }

            $resolved    = $data['resolved'];
            $locationKey = $context['location_key'] ?? 'location_id';

            $dupeKeyParts = [
                $resolved['item_id'] ?? '',
                $resolved['batch_no'] ?? '',
                $resolved[$locationKey] ?? '',
            ];
            $dupeKey = implode('|', $dupeKeyParts);

            if (! isset($dupeIndex[$dupeKey])) {
                $dupeIndex[$dupeKey] = [];
            }
            $dupeIndex[$dupeKey][] = $rowNum;
        }

        $dupeRowNums = [];
        foreach ($dupeIndex as $indices) {
            if (count($indices) > 1) {
                foreach ($indices as $idx) {
                    $dupeRowNums[$idx] = true;
                }
            }
        }

        // ── Build final valid/error lists ────────────────────────────────────
        foreach ($resolvedAll as $rowNum => $data) {
            if ($data['failed']) {
                continue; // errors already added in pass 1/2
            }
            if (isset($dupeRowNums[$rowNum])) {
                $errors[] = [
                    'row'     => $data['row'],
                    'column'  => 'Row',
                    'message' => 'Duplicate row (same item, batch, and location as another row in this upload).',
                ];
                continue;
            }
            $valid[] = $data['resolved'];
        }

        return [
            'valid'     => $valid,
            'errors'    => $errors,
            'total'     => min($total, self::MAX_ROWS),
            'truncated' => $truncated,
        ];
    }

    /**
     * Build an XLSX template with data sheet + Column Guide sheet.
     */
    public function buildTemplate(array $fields, string $sheetName): StreamedResponse
    {
        $headers  = array_column($fields, 'column');
        $example1 = array_column($fields, 'example');
        $example2 = $example1; // duplicate example row for demo
        $example3 = array_fill(0, count($fields), '');

        return response()->streamDownload(function () use ($fields, $sheetName, $headers, $example1, $example2, $example3) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->getCurrentSheet()->setName($sheetName);

            // Header row
            $writer->addRow(Row::fromValues($headers));
            // Three example rows
            $writer->addRow(Row::fromValues($example1));
            $writer->addRow(Row::fromValues($example2));
            $writer->addRow(Row::fromValues($example3));

            // Column Guide sheet
            $writer->addNewSheetAndMakeItCurrent()->setName('Column Guide');
            $writer->addRow(Row::fromValues(['Field', 'Required', 'Format', 'Example']));
            foreach ($fields as $field) {
                $writer->addRow(Row::fromValues([
                    $field['column'],
                    $field['required'] ? 'Yes' : 'No',
                    $field['format'],
                    $field['example'],
                ]));
            }

            $writer->close();
        }, str_replace(' ', '_', strtolower($sheetName)) . '_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function parseSerials(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn ($s) => $s !== ''
        ));
    }
}
