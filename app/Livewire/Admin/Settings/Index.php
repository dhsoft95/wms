<?php

namespace App\Livewire\Admin\Settings;

use App\Actions\Foundation\SaveDocSequenceAction;
use App\Actions\Foundation\SaveReasonCodeAction;
use App\Actions\Foundation\SaveUomAction;
use App\Exceptions\InUseException;
use App\Models\DocumentSequence;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use App\Services\Foundation\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    // ─── Active Tab ───────────────────────────────────────────────────────────

    public string $activeTab = 'company';

    // ─── Company Tab ──────────────────────────────────────────────────────────

    public string $companyName    = '';
    public string $companyAddress = '';
    public string $companyPhone   = '';
    public string $companyEmail   = '';
    public string $companyTin     = '';
    public $logo;

    // ─── Operations Tab ───────────────────────────────────────────────────────
    // Approval thresholds moved to per-item settings. Only global fallback
    // allocation rule (used when an item has no explicit rules) remains.

    public string $allocationRule = 'fifo';

    // ─── Reason Codes Tab ─────────────────────────────────────────────────────

    public string  $reasonCodeSearch = '';
    public ?int    $rcEditing        = null;
    public string  $rcCode           = '';
    public string  $rcName           = '';
    public string  $rcAppliesTo      = '';
    public bool    $rcIsActive       = true;

    // ─── UoM Tab ──────────────────────────────────────────────────────────────

    public string $uomSearch  = '';
    public ?int   $uomEditing = null;
    public string $uomCode    = '';
    public string $uomName    = '';
    public string $uomType    = '';

    // ─── Approval Tab (formerly Outbound) ────────────────────────────────────────

    public bool   $outboundAllowPartial       = true;
    public bool   $outboundAutoBackorder      = true;
    public string $outboundMinShelfLifeDays   = '0';
    public string $outboundShortPickBehaviour = 'leave_short';

    // ─── Vehicle Types Tab ────────────────────────────────────────────────────

    public string $vehicleTypes = '';

    // ─── Document Sequences (mutable state for inline editing) ───────────────

    /** @var array<int, array{prefix: string, padding: int, include_year: bool, reset_yearly: bool}> */
    public array $sequenceData = [];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->companyName    = SettingsService::get('company.name', '');
        $this->companyAddress = SettingsService::get('company.address', '');
        $this->companyPhone   = SettingsService::get('company.phone', '');
        $this->companyEmail   = SettingsService::get('company.email', '');
        $this->companyTin     = SettingsService::get('company.tin', '');

        $this->allocationRule = SettingsService::get('operations.allocation_rule', 'fifo');

        $this->outboundAllowPartial       = (bool) SettingsService::get('outbound.allow_partial_allocation', true);
        $this->outboundAutoBackorder      = (bool) SettingsService::get('outbound.auto_backorder', true);
        $this->outboundMinShelfLifeDays   = (string) SettingsService::get('outbound.min_shelf_life_days', '0');
        $this->outboundShortPickBehaviour = SettingsService::get('outbound.short_pick_behaviour', 'leave_short');

        $this->vehicleTypes = SettingsService::get('vehicle_types', 'Truck,Van,Pickup,Trailer,Other');

        // Pre-populate sequence data for wire:model binding
        foreach (DocumentSequence::orderBy('type')->get() as $seq) {
            $this->sequenceData[$seq->id] = [
                'prefix'       => (string) $seq->prefix,
                'padding'      => (int) $seq->padding,
                'include_year' => (bool) $seq->include_year,
                'reset_yearly' => (bool) $seq->reset_yearly,
            ];
        }
    }

    // ─── Company ──────────────────────────────────────────────────────────────

    public function saveCompany(): void
    {
        $validated = $this->validate([
            'companyName'    => 'required|string|max:255',
            'companyEmail'   => 'nullable|email|max:255',
            'companyPhone'   => 'nullable|string|max:50',
            'companyTin'     => 'nullable|string|max:50',
            'companyAddress' => 'nullable|string|max:500',
            'logo'           => 'nullable|image|max:2048',
        ]);

        SettingsService::set('company.name',    $this->companyName);
        SettingsService::set('company.email',   $this->companyEmail);
        SettingsService::set('company.phone',   $this->companyPhone);
        SettingsService::set('company.tin',     $this->companyTin);
        SettingsService::set('company.address', $this->companyAddress);

        if ($this->logo) {
            $path = $this->logo->store('branding', 'public');
            SettingsService::set('company.logo_path', $path);
            $this->logo = null;
        }

        $this->dispatch('toast', message: 'Company settings saved.', type: 'success');
    }

    // ─── Operations ───────────────────────────────────────────────────────────

    public function saveOperations(): void
    {
        $this->validate([
            'allocationRule' => 'required|string|in:fifo,fefo,lifo',
        ]);

        SettingsService::set('operations.allocation_rule', $this->allocationRule);

        $this->dispatch('toast', message: 'Operations settings saved.', type: 'success');
    }

    // ─── Approval (formerly Outbound) ─────────────────────────────────────────────

    public function saveOutbound(): void
    {
        $this->validate([
            'outboundAllowPartial'       => 'boolean',
            'outboundAutoBackorder'      => 'boolean',
            'outboundMinShelfLifeDays'   => 'nullable|integer|min:0|max:365',
            'outboundShortPickBehaviour' => 'required|string|in:reallocate,leave_short',
        ]);

        SettingsService::set('outbound.allow_partial_allocation', $this->outboundAllowPartial ? '1' : '0', 'boolean');
        SettingsService::set('outbound.auto_backorder',           $this->outboundAutoBackorder ? '1' : '0', 'boolean');
        SettingsService::set('outbound.min_shelf_life_days',      $this->outboundMinShelfLifeDays ?: '0', 'integer');
        SettingsService::set('outbound.short_pick_behaviour',     $this->outboundShortPickBehaviour);

        $this->dispatch('toast', message: 'Outbound settings saved.', type: 'success');
    }

    // ─── Vehicle Types ────────────────────────────────────────────────────────

    public function saveVehicleTypes(): void
    {
        $this->validate([
            'vehicleTypes' => 'nullable|string|max:500',
        ]);

        SettingsService::set('vehicle_types', $this->vehicleTypes);

        $this->dispatch('toast', message: 'Vehicle types saved.', type: 'success');
    }

    // ─── Document Sequences ───────────────────────────────────────────────────

    public function saveSequence(int $id): void
    {
        $this->validate([
            "sequenceData.{$id}.prefix"       => 'nullable|string|max:10',
            "sequenceData.{$id}.padding"      => 'required|integer|min:1|max:10',
            "sequenceData.{$id}.include_year" => 'boolean',
            "sequenceData.{$id}.reset_yearly" => 'boolean',
        ]);

        $data = $this->sequenceData[$id] ?? [];

        SaveDocSequenceAction::execute($id, $data);

        unset($this->sequences);
        $this->dispatch('toast', message: 'Sequence updated.', type: 'success');
    }

    // ─── Reason Codes ─────────────────────────────────────────────────────────

    #[Computed]
    public function reasonCodes(): Collection
    {
        return ReasonCode::when($this->reasonCodeSearch, function ($query) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->reasonCodeSearch . '%')
                  ->orWhere('name', 'like', '%' . $this->reasonCodeSearch . '%');
            });
        })
        ->orderBy('code')
        ->get();
    }

    public function saveReasonCode(): void
    {
        $uniqueRule = $this->rcEditing
            ? "required|string|max:20|unique:reason_codes,code,{$this->rcEditing}"
            : 'required|string|max:20|unique:reason_codes,code';

        $this->validate([
            'rcCode'      => $uniqueRule,
            'rcName'      => 'required|string|max:255',
            'rcAppliesTo' => 'required|string|in:variance,adjustment,short_pick,reprint,hold,seal_mismatch',
            'rcIsActive'  => 'boolean',
        ]);

        $rcData = [
            'code'       => $this->rcCode,
            'name'       => $this->rcName,
            'applies_to' => $this->rcAppliesTo,
            'is_active'  => $this->rcIsActive,
        ];

        if ($this->rcEditing) {
            SaveReasonCodeAction::update($this->rcEditing, $rcData);
            $message = 'Reason code updated.';
        } else {
            SaveReasonCodeAction::create($rcData);
            $message = 'Reason code created.';
        }

        $this->cancelRcEdit();
        unset($this->reasonCodes);
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function editReasonCode(int $id): void
    {
        $rc = ReasonCode::findOrFail($id);

        $this->rcEditing   = $rc->id;
        $this->rcCode      = $rc->code;
        $this->rcName      = $rc->name;
        $this->rcAppliesTo = $rc->applies_to instanceof \App\Enums\ReasonCodeAppliesTo
            ? $rc->applies_to->value
            : (string) $rc->applies_to;
        $this->rcIsActive  = (bool) $rc->is_active;
    }

    public function deleteReasonCode(int $id): void
    {
        SaveReasonCodeAction::delete($id);
        unset($this->reasonCodes);
        $this->dispatch('toast', message: 'Reason code deleted.', type: 'success');
    }

    public function cancelRcEdit(): void
    {
        $this->rcEditing   = null;
        $this->rcCode      = '';
        $this->rcName      = '';
        $this->rcAppliesTo = '';
        $this->rcIsActive  = true;
        $this->resetErrorBag(['rcCode', 'rcName', 'rcAppliesTo', 'rcIsActive']);
    }

    // ─── Units of Measure ─────────────────────────────────────────────────────

    #[Computed]
    public function unitOfMeasures(): Collection
    {
        return UnitOfMeasure::when($this->uomSearch, function ($query) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->uomSearch . '%')
                  ->orWhere('name', 'like', '%' . $this->uomSearch . '%');
            });
        })
        ->orderBy('code')
        ->get();
    }

    public function saveUom(): void
    {
        $uniqueRule = $this->uomEditing
            ? "required|string|max:20|unique:units_of_measure,code,{$this->uomEditing}"
            : 'required|string|max:20|unique:units_of_measure,code';

        $this->validate([
            'uomCode' => $uniqueRule,
            'uomName' => 'required|string|max:100',
            'uomType' => 'required|string|in:count,weight,volume,length',
        ]);

        $uomData = [
            'code' => $this->uomCode,
            'name' => $this->uomName,
            'type' => $this->uomType,
        ];

        if ($this->uomEditing) {
            SaveUomAction::update($this->uomEditing, $uomData);
            $message = 'Unit of measure updated.';
        } else {
            SaveUomAction::create($uomData);
            $message = 'Unit of measure created.';
        }

        $this->cancelUomEdit();
        unset($this->unitOfMeasures);
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function editUom(int $id): void
    {
        $uom = UnitOfMeasure::findOrFail($id);

        $this->uomEditing = $uom->id;
        $this->uomCode    = $uom->code;
        $this->uomName    = $uom->name;
        $this->uomType    = $uom->type instanceof \App\Enums\UomType
            ? $uom->type->value
            : (string) $uom->type;
    }

    public function deleteUom(int $id): void
    {
        try {
            SaveUomAction::delete($id);
        } catch (InUseException $e) {
            $this->dispatch('toast', message: 'Cannot delete: this unit is referenced by existing items.', type: 'error');
            return;
        }

        unset($this->unitOfMeasures);
        $this->dispatch('toast', message: 'Unit of measure deleted.', type: 'success');
    }

    public function cancelUomEdit(): void
    {
        $this->uomEditing = null;
        $this->uomCode    = '';
        $this->uomName    = '';
        $this->uomType    = '';
        $this->resetErrorBag(['uomCode', 'uomName', 'uomType']);
    }

    // ─── Computed: Document Sequences ─────────────────────────────────────────

    #[Computed]
    public function sequences(): Collection
    {
        return DocumentSequence::orderBy('type')->get();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.settings.index')
            ->layout('components.layouts.app', [
                'title'           => 'Settings',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
