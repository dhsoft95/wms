<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\BulkLineImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait WithBulkLineUpload
{
    // ─── State ────────────────────────────────────────────────────────────────

    public bool   $blkOpen   = false;
    /** @var mixed TemporaryUploadedFile|null */
    public        $blkFile   = null;
    public string $blkMode   = 'append';
    public array  $blkValid  = [];
    public array  $blkErrors = [];
    public int    $blkTotal  = 0;
    public bool   $blkParsed = false;

    // ─── Contract (implementing component must define these) ──────────────────
    // public static function lineFields(): array
    // protected function bulkContext(): array
    // protected function applyBulkLines(array $rows): void

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function blkUpload(BulkLineImportService $svc): void
    {
        $this->validate([
            'blkFile' => ['required', 'file', 'mimes:xlsx,csv', 'max:5120'],
        ]);

        // Copy the Livewire temp file to a system temp path that carries the
        // correct extension — SimpleExcelReader detects format by extension.
        $ext  = strtolower($this->blkFile->getClientOriginalExtension() ?: 'xlsx');
        $full = tempnam(sys_get_temp_dir(), 'blk_') . '.' . $ext;
        copy($this->blkFile->getRealPath(), $full);

        $fields = static::lineFields();
        $ctx    = $this->bulkContext();

        $result = $svc->process($full, $fields, $ctx);
        @unlink($full);

        $this->blkValid  = $result['valid'];
        $this->blkErrors = $result['errors'];
        $this->blkTotal  = $result['total'];
        $this->blkParsed = true;
    }

    public function blkConfirm(): void
    {
        if (empty($this->blkValid)) {
            return;
        }

        $this->applyBulkLines($this->blkValid);
        $this->blkReset();
    }

    public function blkReset(): void
    {
        $this->blkFile   = null;
        $this->blkMode   = 'append';
        $this->blkValid  = [];
        $this->blkErrors = [];
        $this->blkTotal  = 0;
        $this->blkParsed = false;
    }

    public function downloadBulkTemplate(BulkLineImportService $svc): StreamedResponse
    {
        $fields = static::lineFields();
        $name   = class_basename(static::class) . ' Lines';

        return $svc->buildTemplate($fields, $name);
    }
}
