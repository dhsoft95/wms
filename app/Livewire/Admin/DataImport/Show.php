<?php

namespace App\Livewire\Admin\DataImport;

use App\Jobs\ConfirmImportJob;
use App\Models\DataImport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\SimpleExcel\SimpleExcelReader;

class Show extends Component
{
    public DataImport $import;

    public function mount(DataImport $import): void
    {
        $this->authorize('imports.manage');
        $this->import = $import;
    }

    public function confirm(): void
    {
        $this->authorize('imports.manage');
        $this->import->refresh();

        if (! $this->import->canConfirm()) {
            $this->dispatch('toast', message: 'Import cannot be confirmed (check for errors or status).', type: 'error');
            return;
        }

        if ($this->import->type->requiresOpeningBalancePermission()) {
            $this->authorize('stock.opening-balance');
        }

        $this->import->update(['confirmed_by' => auth()->id()]);

        ConfirmImportJob::dispatch($this->import);

        $this->dispatch('toast', message: 'Import job queued.', type: 'info');
    }

    // Downloads the original file annotated with an "Errors" column
    public function downloadErrors(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('imports.manage');

        $errors  = $this->import->errors ?? [];
        $byRow   = [];
        foreach ($errors as $e) {
            $byRow[(int) $e['row']][] = "[{$e['column']}] {$e['message']}";
        }

        $path = storage_path('app/' . $this->import->stored_path);

        return response()->streamDownload(function () use ($path, $byRow) {
            $out = fopen('php://output', 'w');

            try {
                $reader = SimpleExcelReader::create($path)->getRows();
                $first  = true;
                $rowNum = 0;

                foreach ($reader as $row) {
                    $rowNum++;
                    if ($first) {
                        fputcsv($out, array_merge(array_keys($row), ['Errors']));
                        $first = false;
                    }
                    $errorText = isset($byRow[$rowNum]) ? implode(' | ', $byRow[$rowNum]) : '';
                    fputcsv($out, array_merge(array_values($row), [$errorText]));
                }
            } catch (\Throwable) {
                // If file unreadable fall back to plain error list
                fputcsv($out, ['row', 'column', 'message']);
                foreach ($byRow as $row => $msgs) {
                    foreach ($msgs as $msg) {
                        fputcsv($out, [$row, '', $msg]);
                    }
                }
            }

            fclose($out);
        }, 'import-errors-' . $this->import->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    #[Computed]
    public function previewErrors(): array
    {
        return array_slice($this->import->errors ?? [], 0, 50);
    }

    #[Computed]
    public function previewRows(): array
    {
        $path = storage_path('app/' . $this->import->stored_path);
        if (! file_exists($path)) {
            return [];
        }

        $errors = $this->import->errors ?? [];
        $errorRowNums = array_unique(array_column($errors, 'row'));

        $rows   = [];
        $count  = 0;

        try {
            foreach (SimpleExcelReader::create($path)->getRows() as $i => $row) {
                $rowNum = $i + 1;
                if (! in_array($rowNum, $errorRowNums, strict: true)) {
                    $rows[] = $row;
                    $count++;
                    if ($count >= 50) {
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            // file may not be readable yet
        }

        return $rows;
    }

    public function render(): View
    {
        $this->import->refresh(); // pick up status updates from the job
        return view('livewire.admin.data-import.show')
            ->layout('components.layouts.app', [
                'title'           => 'Import: ' . $this->import->original_filename,
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
