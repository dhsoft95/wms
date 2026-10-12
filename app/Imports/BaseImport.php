<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\DataImport;
use Spatie\SimpleExcel\SimpleExcelReader;

abstract class BaseImport
{
    protected DataImport $import;

    public function __construct(DataImport $import)
    {
        $this->import = $import;
    }

    // ─── Contract ─────────────────────────────────────────────────────────────

    abstract protected function validateRow(int $rowNumber, array $row): array; // returns [] or [{column, message}]
    abstract protected function importRow(array $row): string; // 'created' or 'updated'

    // Expected column headers for this import type
    abstract public function headers(): array;

    // ─── Template generation ──────────────────────────────────────────────────

    abstract public function templateRows(): array;     // one example row
    abstract public function columnInstructions(): array; // [column => description]

    // ─── Orchestration ────────────────────────────────────────────────────────

    public function validate(): void
    {
        $path   = storage_path('app/' . $this->import->stored_path);
        $reader = SimpleExcelReader::create($path)->getRows();

        $totalRows  = 0;
        $errorRows  = 0;
        $errors     = [];

        foreach ($reader as $rowNumber => $row) {
            $totalRows++;
            $rowErrors = $this->validateRow($rowNumber + 1, $row); // 1-based
            if (! empty($rowErrors)) {
                $errorRows++;
                foreach ($rowErrors as $e) {
                    $errors[] = array_merge(['row' => $rowNumber + 1], $e);
                }
            }
        }

        $this->import->update([
            'total_rows'  => $totalRows,
            'valid_rows'  => $totalRows - $errorRows,
            'error_rows'  => $errorRows,
            'errors'      => $errors,
            'status'      => $errorRows > 0 ? 'failed_validation' : 'validated',
            'summary'     => $this->computeSummary($totalRows, $totalRows - $errorRows, $errorRows),
        ]);
    }

    /**
     * Compute a summary array stored in DataImport.summary after validation.
     * Override in subclasses for import-type-specific summaries.
     */
    protected function computeSummary(int $totalRows, int $validRows, int $errorRows): array
    {
        return [
            'will_create' => $validRows,
            'will_update' => 0,
            'errors'      => $errorRows,
        ];
    }

    public function import(): void
    {
        $path    = storage_path('app/' . $this->import->stored_path);
        $reader  = SimpleExcelReader::create($path)->getRows();
        $created = 0;
        $updated = 0;
        $errors  = [];
        $totalRows = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($reader, &$created, &$updated, &$errors, &$totalRows) {
            foreach ($reader as $rowNumber => $row) {
                $totalRows++;
                $rowErrors = $this->validateRow($rowNumber + 1, $row);
                if (! empty($rowErrors)) {
                    foreach ($rowErrors as $e) {
                        $errors[] = array_merge(['row' => $rowNumber + 1], $e);
                    }
                    continue;
                }

                $result = $this->importRow($row);
                if ($result === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        $this->import->update([
            'created_count' => $created,
            'updated_count' => $updated,
            'errors'        => $errors,
            'total_rows'    => $totalRows,
            'valid_rows'    => $totalRows - count($errors),
            'error_rows'    => count($errors),
            'status'        => empty($errors) ? 'completed' : 'failed',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function err(string $column, string $message): array
    {
        return ['column' => $column, 'message' => $message];
    }

    protected function str(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    protected function req(array $row, string $key, string &$errors = ''): bool
    {
        if ($this->str($row, $key) === '') {
            return false;
        }
        return true;
    }
}
