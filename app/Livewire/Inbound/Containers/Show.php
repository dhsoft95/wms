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
use App\Models\Location;
use App\Models\Partner;
use App\Services\Inbound\ContainerQueryService;
use App\Services\Inbound\ContainerWorkflow;
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
