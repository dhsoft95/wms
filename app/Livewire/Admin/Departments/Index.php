<?php

namespace App\Livewire\Admin\Departments;

use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $search        = '';
    public string $name          = '';
    public string $code          = '';
    public ?int   $editingId     = null;
    public ?int   $confirmingDelete = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('departments.manage'), 403);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::withCount('users')
            ->when($this->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->withTrashed()
            ->orderBy('name')
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('departments', 'code')->ignore($this->editingId)->withoutTrashed(),
            ],
        ];

        $this->validate($rules);

        if ($this->editingId) {
            $department = Department::withTrashed()->findOrFail($this->editingId);
            $department->update(['name' => $this->name, 'code' => strtoupper($this->code)]);
            $this->dispatch('toast', message: "Department \"{$this->name}\" updated.", type: 'success');
        } else {
            Department::create(['name' => $this->name, 'code' => strtoupper($this->code), 'is_active' => true]);
            $this->dispatch('toast', message: "Department \"{$this->name}\" created.", type: 'success');
        }

        $this->resetForm();
    }

    public function startEdit(int $id): void
    {
        $department      = Department::withTrashed()->findOrFail($id);
        $this->editingId = $id;
        $this->name      = $department->name;
        $this->code      = $department->code;
        $this->confirmingDelete = null;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $department = Department::findOrFail($id);
        $department->update(['is_active' => ! $department->is_active]);

        $state = $department->fresh()->is_active ? 'activated' : 'deactivated';
        $this->dispatch('toast', message: "\"{$department->name}\" {$state}.", type: 'info');
    }

    public function deleteOrDeactivate(int $id): void
    {
        $department = Department::withCount('users')->findOrFail($id);

        if ($department->users_count > 0) {
            $department->update(['is_active' => false]);
            $this->dispatch('toast', message: 'Department has users — deactivated instead.', type: 'info');
        } else {
            $department->delete();
            $this->dispatch('toast', message: "\"{$department->name}\" deleted.", type: 'success');
        }

        $this->confirmingDelete = null;
    }

    public function restore(int $id): void
    {
        $department = Department::withTrashed()->findOrFail($id);
        $department->restore();
        $department->update(['is_active' => true]);
        $this->dispatch('toast', message: "\"{$department->name}\" restored.", type: 'success');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->code      = '';
        $this->resetValidation();
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
