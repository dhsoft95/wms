<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Containers;

use App\Enums\ContainerDiscrepancyType;
use App\Enums\ContainerDocumentType;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\ContainerDiscrepancy;
use App\Models\ContainerDocument;
use App\Models\ContainerExpectedLine;
use App\Models\ImportMappingProfile;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\Partner;
use App\Services\Inbound\ContainerQueryService;
use App\Services\Inbound\ContainerWorkflow;
use App\Services\Inbound\PackingListParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Container $container;
    public string $activeTab = 'overview';

    // Arrival modal
    public bool   $arrivalModalOpen = false;
    public string $arrivedAt        = '';
    public string $arrivalSealNo    = '';

    // Park modal
    public bool   $parkModalOpen   = false;
    public ?int   $parkLocationId  = null;

    // Move slot modal
    public bool   $moveModalOpen   = false;
    public ?int   $moveLocationId  = null;
    public string $moveReason      = '';

    // Release modal
    public bool   $releaseModalOpen        = false;
    public string $releasedAt              = '';
    public ?int   $releasedToPartnerId     = null;
    public string $releaseNotes            = '';

    // Cancel modal
    public bool   $cancelModalOpen = false;
    public string $cancelReason    = '';

    // Add/Edit Line modal
    public bool   $lineModalOpen   = false;
    public ?int   $editingLineId   = null;
    public ?int   $lineItemId      = null;
    public string $lineRawSku      = '';
    public string $lineRawDesc     = '';
    public string $lineQty         = '';
    public ?int   $lineUomId       = null;
    public string $lineBatchNo     = '';
    public string $lineExpiry      = '';
    public string $lineWeightKg    = '';
    public ?int   $lineVwId        = null;
    public string $lineNotes       = '';

    // Document upload
    public bool   $docModalOpen = false;
    public $docFile             = null;
    public string $docType      = 'other';

    // Discrepancy modal
    public bool   $discModalOpen     = false;
    public ?int   $editingDiscId     = null;
    public string $discType          = 'other';
    public string $discDescription   = '';
    public string $discResolveNotes  = '';

    // Packing List Import wizard
    public bool   $plOpen          = false;
    public int    $plStep          = 1;
    public        $plFile          = null;
    public string $plStoredPath    = '';
    public array  $plSampleRows    = [];
    public int    $plHeaderRowIdx  = 0;
    public array  $plFileColumns   = [];
    public array  $plMapping       = [];
    public ?int   $plProfileId     = null;
    public bool   $plSaveProfile   = false;
    public string $plProfileName   = '';
    public array  $plRows          = [];
    public array  $plItemOverrides = [];
    public string $plMode          = 'replace';
    public bool   $plQcOpen        = false;
    public int    $plQcRowIdx      = -1;
    public string $plQcSku         = '';
    public string $plQcName        = '';
    public ?int   $plQcCategoryId  = null;
    public ?int   $plQcBaseUomId   = null;
    public bool   $plQcTrackBatch  = false;
    public bool   $plQcTrackExpiry = false;

    public function mount(Container $container, ContainerQueryService $queryService): void
    {
        abort_unless($queryService->canAccess(auth()->user(), $container), 404);
        $this->container  = $container;
        $this->arrivedAt  = now()->format('Y-m-d\TH:i');
        $this->releasedAt = now()->format('Y-m-d\TH:i');
    }

    // ── Computed ────────────────────────────────────────────────────────────────

    #[Computed]
    public function freeYardSlots()
    {
        $activeStatuses = array_column(
            array_filter(ContainerStatus::cases(), fn($s) => $s->isActive()),
            'value'
        );

        $occupiedIds = Container::whereNotNull('yard_location_id')
            ->where('id', '!=', $this->container->id)
            ->whereIn('status', $activeStatuses)
            ->pluck('yard_location_id');

        return Location::where('type', 'yard_slot')
            ->where('status', 'active')
            ->whereNotIn('id', $occupiedIds)
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function releasePartners()
    {
        return Partner::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return \App\Models\VirtualWarehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function items()
    {
        return \App\Models\Item::where('is_active', true)->orderBy('name')->limit(500)->get();
    }

    #[Computed]
    public function uoms()
    {
        return \App\Models\UnitOfMeasure::orderBy('name')->get();
    }

    #[Computed]
    public function plProfiles()
    {
        return ImportMappingProfile::where('import_type', 'container_packing_list')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function plCategories()
    {
        return ItemCategory::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function plBaseUoms()
    {
        return \App\Models\UnitOfMeasure::where('is_active', true)->orderBy('code')->get();
    }

    // ── Workflow Actions ────────────────────────────────────────────────────────

    public function confirmArrival(ContainerWorkflow $workflow): void
    {
        $this->authorize('arrive', $this->container);
        $this->validate([
            'arrivedAt'     => 'required|date',
            'arrivalSealNo' => 'required|string|max:50',
        ]);
        try {
            $workflow->confirmArrival(
                $this->container,
                new \DateTime($this->arrivedAt),
                $this->arrivalSealNo,
                auth()->user()
            );
            $this->container->refresh();
            $this->arrivalModalOpen = false;
            $this->dispatch('toast', message: 'Arrival confirmed.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function park(ContainerWorkflow $workflow): void
    {
        $this->authorize('park', $this->container);
        $this->validate(['parkLocationId' => 'required|integer|exists:locations,id']);
        try {
            $location = Location::findOrFail($this->parkLocationId);
            $workflow->park($this->container, $location, auth()->user());
            $this->container->refresh();
            $this->parkModalOpen = false;
            $this->dispatch('toast', message: 'Container parked at ' . $location->code . '.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function moveSlot(ContainerWorkflow $workflow): void
    {
        $this->authorize('moveSlot', $this->container);
        $this->validate([
            'moveLocationId' => 'required|integer|exists:locations,id',
            'moveReason'     => 'required|string|max:255',
        ]);
        try {
            $location = Location::findOrFail($this->moveLocationId);
            $workflow->moveSlot($this->container, $location, $this->moveReason, auth()->user());
            $this->container->refresh();
            $this->moveModalOpen = false;
            $this->dispatch('toast', message: 'Container moved to ' . $location->code . '.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function startUnloading(ContainerWorkflow $workflow): void
    {
        $this->authorize('startUnloading', $this->container);
        try {
            $workflow->startUnloading($this->container, auth()->user());
            $this->container->refresh();
            $this->dispatch('toast', message: 'Unloading started.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function markUnloaded(ContainerWorkflow $workflow): void
    {
        $this->authorize('markUnloaded', $this->container);
        try {
            $workflow->markUnloaded($this->container, auth()->user());
            $this->container->refresh();
            $this->dispatch('toast', message: 'Container marked as unloaded.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function release(ContainerWorkflow $workflow): void
    {
        $this->authorize('release', $this->container);
        $this->validate([
            'releasedAt'          => 'required|date',
            'releasedToPartnerId' => 'required|integer|exists:partners,id',
            'releaseNotes'        => 'nullable|string',
        ]);
        try {
            $workflow->release(
                $this->container,
                new \DateTime($this->releasedAt),
                $this->releasedToPartnerId,
                $this->releaseNotes ?: null,
                auth()->user()
            );
            $this->container->refresh();
            $this->releaseModalOpen = false;
            $this->dispatch('toast', message: 'Container released.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(ContainerWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->container);
        $this->validate(['cancelReason' => 'required|string|min:10']);
        try {
            $workflow->cancel($this->container, $this->cancelReason, auth()->user());
            $this->container->refresh();
            $this->cancelModalOpen = false;
            $this->dispatch('toast', message: 'Container cancelled.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ── Expected Lines ──────────────────────────────────────────────────────────

    public function openLineModal(?int $lineId = null): void
    {
        abort_unless($this->container->linesEditable(), 403);
        $this->editingLineId = $lineId;
        if ($lineId) {
            $line = ContainerExpectedLine::findOrFail($lineId);
            $this->lineItemId   = $line->item_id;
            $this->lineRawSku   = $line->raw_sku ?? '';
            $this->lineRawDesc  = $line->raw_description ?? '';
            $this->lineQty      = (string) $line->qty;
            $this->lineUomId    = $line->uom_id;
            $this->lineBatchNo  = $line->batch_no ?? '';
            $this->lineExpiry   = $line->expiry_date?->format('Y-m-d') ?? '';
            $this->lineWeightKg = (string) ($line->weight_kg ?? '');
            $this->lineVwId     = $line->virtual_warehouse_id;
            $this->lineNotes    = $line->notes ?? '';
        } else {
            $this->reset(['lineItemId', 'lineRawSku', 'lineRawDesc', 'lineQty', 'lineUomId', 'lineBatchNo', 'lineExpiry', 'lineWeightKg', 'lineVwId', 'lineNotes']);
        }
        $this->lineModalOpen = true;
    }

    public function saveLine(): void
    {
        abort_unless($this->container->linesEditable(), 403);
        $this->validate([
            'lineQty'  => 'required|numeric|min:0.0001',
            'lineVwId' => 'required|integer|exists:virtual_warehouses,id',
        ]);

        $data = [
            'container_id'         => $this->container->id,
            'item_id'              => $this->lineItemId,
            'raw_sku'              => $this->lineRawSku ?: null,
            'raw_description'      => $this->lineRawDesc ?: null,
            'qty'                  => $this->lineQty,
            'uom_id'               => $this->lineUomId,
            'batch_no'             => $this->lineBatchNo ?: null,
            'expiry_date'          => $this->lineExpiry ?: null,
            'weight_kg'            => $this->lineWeightKg !== '' ? $this->lineWeightKg : null,
            'virtual_warehouse_id' => $this->lineVwId,
            'notes'                => $this->lineNotes ?: null,
        ];

        if ($this->editingLineId) {
            ContainerExpectedLine::findOrFail($this->editingLineId)->update($data);
        } else {
            $maxLine      = $this->container->expectedLines()->max('line_no') ?? 0;
            $data['line_no'] = $maxLine + 1;
            ContainerExpectedLine::create($data);
        }

        $this->lineModalOpen = false;
        $this->container->refresh();
        $this->dispatch('toast', message: 'Line saved.', type: 'success');
    }

    public function deleteLine(int $lineId): void
    {
        abort_unless($this->container->linesEditable(), 403);
        ContainerExpectedLine::findOrFail($lineId)->delete();
        $this->container->refresh();
        $this->dispatch('toast', message: 'Line removed.', type: 'success');
    }

    // ── Packing List Import Wizard ──────────────────────────────────────────────

    public function openPackingListWizard(): void
    {
        $this->authorize('update', $this->container);
        abort_unless($this->container->linesEditable(), 403);
        $this->resetPlWizard();
        $this->plOpen = true;
    }

    public function closePlWizard(): void
    {
        $this->plCleanTempFile();
        $this->resetPlWizard();
    }

    private function resetPlWizard(): void
    {
        $this->plOpen         = false;
        $this->plStep         = 1;
        $this->plFile         = null;
        $this->plStoredPath   = '';
        $this->plSampleRows   = [];
        $this->plHeaderRowIdx = 0;
        $this->plFileColumns  = [];
        $this->plMapping      = [];
        $this->plProfileId    = null;
        $this->plSaveProfile  = false;
        $this->plProfileName  = '';
        $this->plRows         = [];
        $this->plItemOverrides = [];
        $this->plMode         = 'replace';
        $this->plQcOpen       = false;
        $this->plQcRowIdx     = -1;
        $this->plQcSku        = '';
        $this->plQcName       = '';
        $this->plQcCategoryId = null;
        $this->plQcBaseUomId  = null;
        $this->plQcTrackBatch = false;
        $this->plQcTrackExpiry = false;
    }

    /** Step 1 → 3: upload file, auto-detect header, auto-map columns. Goes straight to mapping. */
    public function plUploadAndDetect(PackingListParser $parser): void
    {
        $this->authorize('update', $this->container);
        $this->validate([
            'plFile' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $name = now()->format('Ymd_His') . '_' . $this->plFile->getClientOriginalName();
        $path = $this->plFile->storeAs('packing-lists', $name, 'local');
        $this->plStoredPath   = $path;
        $fullPath             = storage_path('app/' . $path);
        $this->plSampleRows   = $parser->readRawRows($fullPath, 15);
        $this->plHeaderRowIdx = $parser->detectHeaderRow($this->plSampleRows);

        $this->plApplyHeaderAndMap($parser);
        $this->plStep = 3;
    }

    /** Step 2 → 3: called when user manually picks a different header row. */
    public function plConfirmHeader(PackingListParser $parser): void
    {
        $this->plApplyHeaderAndMap($parser);
        $this->plStep = 3;
    }

    /** Shared: extract columns from current header row index and auto-map. */
    private function plApplyHeaderAndMap(PackingListParser $parser): void
    {
        $headerRow = $this->plSampleRows[$this->plHeaderRowIdx] ?? [];
        $this->plFileColumns = array_values(
            array_filter(array_map('strval', $headerRow), fn ($v) => $v !== '')
        );

        $this->plMapping = $parser->autoMapColumns($this->plFileColumns);

        $shipperId = $this->container->shipper_partner_id;
        if ($shipperId) {
            $profile = ImportMappingProfile::where('import_type', 'container_packing_list')
                ->where('partner_id', $shipperId)
                ->where('is_active', true)
                ->latest()
                ->first();
            if ($profile) {
                $this->plProfileId = $profile->id;
                foreach ($this->plFileColumns as $col) {
                    if (isset($profile->mapping[$col])) {
                        $this->plMapping[$col] = $profile->mapping[$col];
                    }
                }
            }
        }

        $this->plProfileName = $this->container->shipperPartner?->name ?? 'Packing List';
    }

    /** Apply a selected saved profile to the current mapping. */
    public function plApplyProfile(): void
    {
        if (!$this->plProfileId) {
            return;
        }
        $profile = ImportMappingProfile::find($this->plProfileId);
        if (!$profile) {
            return;
        }
        foreach ($this->plFileColumns as $col) {
            if (isset($profile->mapping[$col])) {
                $this->plMapping[$col] = $profile->mapping[$col];
            }
        }
    }

    /** Step 3 → 4: save profile if requested, parse and resolve all rows. */
    public function plProcessMapping(PackingListParser $parser): void
    {
        if ($this->plSaveProfile && trim($this->plProfileName) !== '') {
            ImportMappingProfile::updateOrCreate(
                [
                    'import_type' => 'container_packing_list',
                    'partner_id'  => $this->container->shipper_partner_id,
                    'name'        => trim($this->plProfileName),
                ],
                [
                    'mapping'    => $this->plMapping,
                    'is_active'  => true,
                    'created_by' => auth()->id(),
                ]
            );
        }

        $fullPath   = storage_path('app/' . $this->plStoredPath);
        $parsedRows = $parser->parseDataRows($fullPath, $this->plHeaderRowIdx, $this->plMapping);
        $this->plRows  = array_map(fn ($r) => $parser->resolveRow($r), $parsedRows);
        $this->plItemOverrides = [];
        $this->plStep  = 4;
    }

    /** Called by Livewire when plRows[idx].vw_id changes inline in step 4. */
    public function updatedPlRows(string $value, string $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) !== 2 || $parts[1] !== 'vw_id') {
            return;
        }
        $rowIdx = (int) $parts[0];
        if (!isset($this->plRows[$rowIdx])) {
            return;
        }
        $vwId = ($value !== '' && $value !== '0') ? (int) $value : null;
        $vw   = $vwId ? \App\Models\VirtualWarehouse::find($vwId) : null;
        $this->plRows[$rowIdx]['vw_id']   = $vw?->id;
        $this->plRows[$rowIdx]['vw_name'] = $vw?->name;
        $this->plRows[$rowIdx]['errors']  = app(PackingListParser::class)->validate($this->plRows[$rowIdx]);
    }

    /** Called by Livewire when plItemOverrides[idx] changes; re-resolves that row. */
    public function updatedPlItemOverrides(string $value, string $key): void
    {
        $rowIdx = (int) $key;
        if (!isset($this->plRows[$rowIdx])) {
            return;
        }
        $itemId = ($value !== '' && $value !== '0') ? (int) $value : null;
        if ($itemId) {
            $parser = app(PackingListParser::class);
            $this->plRows[$rowIdx] = $parser->resolveWithOverride($this->plRows[$rowIdx], $itemId);
            $this->plRows[$rowIdx]['override_item_id'] = $itemId;
        }
    }

    /** Open the quick-create item sub-modal for an unknown-SKU row. */
    public function plOpenQuickCreate(int $rowIdx): void
    {
        $this->plQcRowIdx     = $rowIdx;
        $this->plQcSku        = $this->plRows[$rowIdx]['raw_sku'] ?? '';
        $this->plQcName       = $this->plRows[$rowIdx]['raw_description'] ?? '';
        $this->plQcCategoryId = null;
        $this->plQcBaseUomId  = null;
        $this->plQcTrackBatch = false;
        $this->plQcTrackExpiry = false;
        $this->plQcOpen       = true;
    }

    /** Create a new item from the quick-create modal and apply it to the row. */
    public function plQuickCreateItem(PackingListParser $parser): void
    {
        $this->validate([
            'plQcSku'        => 'required|string|max:100|unique:items,sku',
            'plQcName'       => 'required|string|max:255',
            'plQcCategoryId' => 'required|exists:item_categories,id',
            'plQcBaseUomId'  => 'required|exists:units_of_measure,id',
        ]);

        $item = Item::create([
            'sku'          => $this->plQcSku,
            'name'         => $this->plQcName,
            'category_id'  => $this->plQcCategoryId,
            'base_uom_id'  => $this->plQcBaseUomId,
            'track_batch'  => $this->plQcTrackBatch,
            'track_expiry' => $this->plQcTrackExpiry,
            'track_serial' => false,
            'is_active'    => true,
        ]);
        $item->uoms()->create(['uom_id' => $this->plQcBaseUomId, 'factor_to_base' => '1.0000']);

        $rowIdx = $this->plQcRowIdx;
        if (isset($this->plRows[$rowIdx])) {
            $this->plItemOverrides[$rowIdx] = $item->id;
            $this->plRows[$rowIdx]          = $parser->resolveWithOverride($this->plRows[$rowIdx], $item->id);
        }

        $this->plQcOpen = false;
        $this->dispatch('toast', message: 'Item created: ' . $item->sku, type: 'success');
    }

    /** Final step: persist lines to the container. */
    public function plConfirmImport(): void
    {
        $this->authorize('update', $this->container);
        abort_unless($this->container->linesEditable(), 403);

        $noVw = array_filter($this->plRows, fn ($r) => !($r['vw_id'] ?? null));
        if (!empty($noVw)) {
            $this->dispatch('toast', message: count($noVw) . ' line(s) still have no Virtual Warehouse.', type: 'error');
            return;
        }

        DB::transaction(function () {
            if ($this->plMode === 'replace') {
                $this->container->expectedLines()->delete();
            }

            $maxLine = $this->plMode === 'append'
                ? ($this->container->expectedLines()->max('line_no') ?? 0)
                : 0;

            foreach ($this->plRows as $row) {
                $maxLine++;
                ContainerExpectedLine::create([
                    'container_id'         => $this->container->id,
                    'line_no'              => $maxLine,
                    'item_id'              => $row['item_id'],
                    'raw_sku'              => $row['raw_sku'] !== '' ? $row['raw_sku'] : null,
                    'raw_description'      => $row['raw_description'] !== '' ? $row['raw_description'] : null,
                    'qty'                  => $row['qty_base'] > 0 ? $row['qty_base'] : (float) $row['raw_qty'],
                    'uom_id'               => $row['base_uom_id'] ?? $row['uom_id'],
                    'batch_no'             => $row['raw_batch'] !== '' ? $row['raw_batch'] : null,
                    'expiry_date'          => $row['raw_expiry'] !== '' ? $row['raw_expiry'] : null,
                    'weight_kg'            => $row['raw_weight'] !== '' ? $row['raw_weight'] : null,
                    'virtual_warehouse_id' => $row['vw_id'],
                ]);
            }
        });

        $lineCount = count($this->plRows);
        $this->plCleanTempFile();
        $this->resetPlWizard();
        $this->container->refresh();
        $this->activeTab = 'lines';
        $this->dispatch('toast', message: $lineCount . ' lines imported.', type: 'success');
    }

    private function plCleanTempFile(): void
    {
        if ($this->plStoredPath) {
            Storage::disk('local')->delete($this->plStoredPath);
        }
    }

    // ── Documents ───────────────────────────────────────────────────────────────

    public function uploadDocument(): void
    {
        $this->authorize('uploadDocument', $this->container);
        $this->validate([
            'docFile' => 'required|file|max:20480',
            'docType' => 'required|string',
        ]);
        $path = $this->docFile->storeAs(
            'container-documents/' . $this->container->id,
            now()->format('Ymd_His') . '_' . $this->docFile->getClientOriginalName(),
            'local'
        );
        ContainerDocument::create([
            'container_id'  => $this->container->id,
            'type'          => $this->docType,
            'original_name' => $this->docFile->getClientOriginalName(),
            'path'          => $path,
            'mime'          => $this->docFile->getMimeType(),
            'size'          => $this->docFile->getSize(),
            'uploaded_by'   => auth()->id(),
        ]);
        $this->docModalOpen = false;
        $this->docFile      = null;
        $this->container->refresh();
        $this->dispatch('toast', message: 'Document uploaded.', type: 'success');
    }

    public function deleteDocument(int $docId): void
    {
        $doc = ContainerDocument::findOrFail($docId);
        $this->authorize('deleteDocument', $this->container);
        Storage::disk('local')->delete($doc->path);
        $doc->delete();
        $this->container->refresh();
        $this->dispatch('toast', message: 'Document deleted.', type: 'success');
    }

    // ── Discrepancies ────────────────────────────────────────────────────────────

    public function openDiscModal(?int $discId = null): void
    {
        $this->editingDiscId = $discId;
        if ($discId) {
            $disc              = ContainerDiscrepancy::findOrFail($discId);
            $this->discType        = $disc->type->value;
            $this->discDescription = $disc->description;
        } else {
            $this->discType        = 'other';
            $this->discDescription = '';
        }
        $this->discModalOpen = true;
    }

    public function saveDiscrepancy(): void
    {
        $this->authorize('addDiscrepancy', $this->container);
        $this->validate([
            'discType'        => 'required|string',
            'discDescription' => 'required|string|min:5',
        ]);

        if ($this->editingDiscId) {
            ContainerDiscrepancy::findOrFail($this->editingDiscId)->update([
                'type'        => $this->discType,
                'description' => $this->discDescription,
            ]);
        } else {
            ContainerDiscrepancy::create([
                'container_id' => $this->container->id,
                'type'         => $this->discType,
                'description'  => $this->discDescription,
                'reported_by'  => auth()->id(),
            ]);
        }

        $this->discModalOpen = false;
        $this->container->refresh();
        $this->dispatch('toast', message: 'Discrepancy saved.', type: 'success');
    }

    public function resolveDiscrepancy(int $discId): void
    {
        $this->authorize('resolveDiscrepancy', $this->container);
        ContainerDiscrepancy::findOrFail($discId)->update([
            'resolved_at'      => now(),
            'resolved_by'      => auth()->id(),
            'resolution_notes' => $this->discResolveNotes ?: null,
        ]);
        $this->container->refresh();
        $this->dispatch('toast', message: 'Discrepancy resolved.', type: 'success');
    }

    public function render()
    {
        $container = $this->container->load([
            'shipperPartner', 'consigneePartner', 'shippingLinePartner', 'releasedToPartner',
            'yardLocation', 'createdBy',
            'expectedLines.item', 'expectedLines.uom', 'expectedLines.virtualWarehouse',
            'discrepancies.reportedBy', 'discrepancies.resolvedBy',
            'documents.uploadedBy',
            'statusHistory.user',
            'yardMoves.fromLocation', 'yardMoves.toLocation', 'yardMoves.user',
        ]);

        return view('livewire.inbound.containers.show', ['container' => $container])
            ->layout('components.layouts.app', [
                'title'           => $container->container_no,
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
