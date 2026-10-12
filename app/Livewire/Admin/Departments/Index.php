<?php

namespace App\Livewire\Admin\Departments;

use App\Actions\Foundation\DeleteDepartmentAction;
use App\Actions\Foundation\DeleteVirtualWarehouseAction;
use App\Exceptions\InUseException;
use App\Livewire\Forms\DepartmentForm;
use App\Livewire\Forms\VirtualWarehouseForm;
use App\Models\Department;
use App\Models\User;
use App\Models\VirtualWarehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public DepartmentForm       $deptForm;
    public VirtualWarehouseForm $vwForm;

    // Toolbar
    public string $search       = '';
    public string $filterStatus = 'all'; // all | active | inactive

    // Expandable rows
    public array $expandedDepts = [];

    // Dept modal
    public bool $showDeptModal = false;
    public ?int $editingDeptId = null;

    // VW modal
    public bool $showVwModal = false;
    public ?int $editingVwId = null;
    public ?int $vwForDeptId = null;

    // Confirm modal
    public bool   $showConfirmModal  = false;
    public string $confirmType       = ''; // 'dept' | 'vw'
    public string $confirmAction     = ''; // 'delete-or-deactivate' | 'activate'
    public int    $confirmId         = 0;
    public string $confirmName       = '';
    public bool   $confirmInUse      = false;
    public int    $confirmUsersCount = 0;
    public int    $confirmVwsCount   = 0;
    public int    $confirmItemsCount = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('departments.manage'), 403);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::with([
            'head',
            'virtualWarehouses' => fn ($q) => $q->withCount(['users', 'items'])->withTrashed()->orderBy('name'),
        ])
            ->withCount(['users', 'virtualWarehouses'])
            ->when($this->search, fn ($q, $s) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")
            ))
            ->when($this->filterStatus === 'active', fn ($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->when($this->filterStatus === 'inactive', fn ($q) => $q->where('is_active', false)->whereNull('deleted_at'))
            ->withTrashed()
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    // ─── Dept Modal ───────────────────────────────────────────────────────────

    public function openCreateDept(): void
    {
        $this->editingDeptId = null;
        $this->deptForm->reset();
        $this->resetValidation();
        $this->showDeptModal = true;
    }

    public function openEditDept(int $id): void
    {
        $dept = Department::withTrashed()->findOrFail($id);
        $this->editingDeptId = $id;
        $this->deptForm->fillFrom($dept);
        $this->resetValidation();
        $this->showDeptModal = true;
    }

    public function saveDept(): void
    {
        $this->validate([
            'deptForm.name'        => ['required', 'string', 'min:2', 'max:100'],
            'deptForm.code'        => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($this->editingDeptId)],
            'deptForm.headUserId'  => ['nullable', 'exists:users,id'],
            'deptForm.reportEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $data = $this->deptForm->toSave();

        if ($this->editingDeptId) {
            Department::withTrashed()->findOrFail($this->editingDeptId)->update($data);
            $this->dispatch('toast', message: "Department \"{$data['name']}\" updated.", type: 'success');
        } else {
            Department::create($data);
            $this->dispatch('toast', message: "Department \"{$data['name']}\" created.", type: 'success');
        }

        $this->closeDeptModal();
        unset($this->departments);
    }

    public function closeDeptModal(): void
    {
        $this->showDeptModal = false;
        $this->editingDeptId = null;
        $this->resetValidation();
    }

    // ─── VW Modal ─────────────────────────────────────────────────────────────

    public function openCreateVw(int $deptId): void
    {
        abort_unless(auth()->user()->can('virtual-warehouses.manage'), 403);
        $this->vwForDeptId = $deptId;
        $this->editingVwId = null;
        $this->vwForm->reset();
        $this->resetValidation();
        $this->showVwModal = true;
    }

    public function openEditVw(int $vwId): void
    {
        abort_unless(auth()->user()->can('virtual-warehouses.manage'), 403);
        $vw = VirtualWarehouse::withTrashed()->findOrFail($vwId);
        $this->vwForDeptId = $vw->department_id;
        $this->editingVwId = $vwId;
        $this->vwForm->fillFrom($vw);
        $this->resetValidation();
        $this->showVwModal = true;
    }

    public function saveVw(): void
    {
        abort_unless(auth()->user()->can('virtual-warehouses.manage'), 403);

        $this->validate([
            'vwForm.name' => ['required', 'string', 'min:2', 'max:100'],
            'vwForm.code' => ['required', 'string', 'max:20', Rule::unique('virtual_warehouses', 'code')->ignore($this->editingVwId)],
        ]);

        $data = $this->vwForm->toSave();

        if ($this->editingVwId) {
            VirtualWarehouse::withTrashed()->findOrFail($this->editingVwId)->update($data);
            $this->dispatch('toast', message: "Virtual warehouse \"{$data['name']}\" updated.", type: 'success');
        } else {
            VirtualWarehouse::create(array_merge($data, ['department_id' => $this->vwForDeptId]));
            $this->dispatch('toast', message: "Virtual warehouse \"{$data['name']}\" created.", type: 'success');
        }

        $this->closeVwModal();
        unset($this->departments);
    }

    public function closeVwModal(): void
    {
        $this->showVwModal = false;
        $this->editingVwId = null;
        $this->vwForDeptId = null;
        $this->resetValidation();
    }

    // ─── Confirm Modal ────────────────────────────────────────────────────────

    public function openConfirmDeleteOrDeactivate(string $type, int $id): void
    {
        if ($type === 'dept') {
            $model = Department::withCount(['users', 'virtualWarehouses'])->findOrFail($id);
            $this->confirmInUse      = $model->isInUse();
            $this->confirmUsersCount = $model->users_count;
            $this->confirmVwsCount   = $model->virtual_warehouses_count;
            $this->confirmItemsCount = 0;
        } else {
            $model = VirtualWarehouse::withCount(['users', 'items'])->findOrFail($id);
            $this->confirmInUse      = $model->isInUse();
            $this->confirmUsersCount = $model->users_count;
            $this->confirmVwsCount   = 0;
            $this->confirmItemsCount = $model->items_count ?? 0;
        }

        $this->confirmType   = $type;
        $this->confirmAction = 'delete-or-deactivate';
        $this->confirmId     = $id;
        $this->confirmName   = $model->name;
        $this->showConfirmModal = true;
    }

    public function openConfirmActivate(string $type, int $id): void
    {
        $model = $type === 'dept'
            ? Department::findOrFail($id)
            : VirtualWarehouse::findOrFail($id);

        $this->confirmType       = $type;
        $this->confirmAction     = 'activate';
        $this->confirmId         = $id;
        $this->confirmName       = $model->name;
        $this->confirmInUse      = false;
        $this->confirmUsersCount = 0;
        $this->confirmVwsCount   = 0;
        $this->confirmItemsCount = 0;
        $this->showConfirmModal  = true;
    }

    public function executeConfirm(): void
    {
        if ($this->confirmAction === 'activate') {
            if ($this->confirmType === 'dept') {
                Department::findOrFail($this->confirmId)->update(['is_active' => true]);
            } else {
                VirtualWarehouse::findOrFail($this->confirmId)->update(['is_active' => true]);
            }
            $this->dispatch('toast', message: "\"{$this->confirmName}\" activated.", type: 'success');
        } else {
            if ($this->confirmType === 'dept') {
                $dept   = Department::findOrFail($this->confirmId);
                $result = DeleteDepartmentAction::execute($dept);
                $this->dispatch('toast',
                    message: $result === 'deactivated'
                        ? "\"{$this->confirmName}\" deactivated (has users or VWs)."
                        : "\"{$this->confirmName}\" deleted.",
                    type: 'success'
                );
            } else {
                $vw = VirtualWarehouse::findOrFail($this->confirmId);
                if ($this->confirmInUse) {
                    $vw->update(['is_active' => false]);
                    $this->dispatch('toast', message: "\"{$this->confirmName}\" deactivated.", type: 'success');
                } else {
                    try {
                        DeleteVirtualWarehouseAction::execute($vw);
                        $this->dispatch('toast', message: "\"{$this->confirmName}\" deleted.", type: 'success');
                    } catch (InUseException) {
                        $this->dispatch('toast', message: 'Cannot delete — VW has assigned users or items.', type: 'error');
                    }
                }
            }
        }

        $this->closeConfirmModal();
        unset($this->departments);
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
    }

    // ─── Row expand / restore ─────────────────────────────────────────────────

    public function toggleExpand(int $deptId): void
    {
        if (in_array($deptId, $this->expandedDepts, true)) {
            $this->expandedDepts = array_values(array_filter($this->expandedDepts, fn ($id) => $id !== $deptId));
        } else {
            $this->expandedDepts[] = $deptId;
        }
    }

    public function restore(int $id, string $type = 'dept'): void
    {
        if ($type === 'vw') {
            $model = VirtualWarehouse::withTrashed()->findOrFail($id);
        } else {
            $model = Department::withTrashed()->findOrFail($id);
        }

        $model->restore();
        $model->update(['is_active' => true]);
        $this->dispatch('toast', message: "\"{$model->name}\" restored.", type: 'success');
        unset($this->departments);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.departments.index')
            ->layout('components.layouts.app', [
                'title'           => 'Departments',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
