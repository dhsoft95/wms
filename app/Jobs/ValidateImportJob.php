<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportType;
use App\Imports\DepartmentVwImport;
use App\Imports\ItemCategoryImport;
use App\Imports\ItemImport;
use App\Imports\LocationImport;
use App\Imports\OpeningStockImport;
use App\Imports\PartnerImport;
use App\Models\DataImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateImportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600;

    public function __construct(public DataImport $import) {}

    public function uniqueId(): string
    {
        return "validate-import-{$this->import->id}";
    }

    public function handle(): void
    {
        $this->import->update(['status' => 'validating']);

        try {
            $handler = $this->resolveHandler();
            $handler->validate();
        } catch (\Throwable $e) {
            $this->import->update([
                'status' => 'failed_validation',
                'errors' => [['row' => 0, 'column' => 'file', 'message' => $e->getMessage()]],
            ]);
        }
    }

    private function resolveHandler(): \App\Imports\BaseImport
    {
        return match($this->import->type) {
            ImportType::DepartmentsVws  => new DepartmentVwImport($this->import),
            ImportType::Partners        => new PartnerImport($this->import),
            ImportType::ItemCategories  => new ItemCategoryImport($this->import),
            ImportType::Items           => new ItemImport($this->import),
            ImportType::Locations       => new LocationImport($this->import),
            ImportType::OpeningStock    => new OpeningStockImport($this->import),
        };
    }
}
