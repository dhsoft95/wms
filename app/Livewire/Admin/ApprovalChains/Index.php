<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ApprovalChains;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Models\ApprovalChain;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    // ─── Filters ──────────────────────────────────────────────────────────────

    public string $docType    = 'po';
    public string $deptSearch = '';

    // ─── Chain Modal ──────────────────────────────────────────────────────────

    public bool   $showChainModal = false;
    public ?int   $editChainId    = null;
    public string $chainDeptId    = '';
    public string $chainDocType   = 'po';
    public string $chainLevel     = '1';

    // ─── Users Modal ──────────────────────────────────────────────────────────

    public bool   $showUsersModal = false;
    public ?int   $manageChainId  = null;
    public string $addUserId      = '';

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::where('is_active', true)
            ->when($this->deptSearch, fn ($q) => $q->where('name', 'like', "%{$this->deptSearch}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function chains(): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovalChain::with(['department', 'chainUsers.user'])
            ->where('document_type', $this->docType)
            ->orderBy('department_id')
            ->orderBy('level')
            ->get();
    }

    #[Computed]
    public function availableUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'email']);
    }

    // ─── Chain CRUD ───────────────────────────────────────────────────────────

    public function openCreateChain(): void
    {
        $this->editChainId    = null;
        $this->chainDeptId    = '';
        $this->chainDocType   = $this->docType;
        $this->chainLevel     = '1';
        $this->showChainModal = true;
        $this->resetValidation();
    }

    public function openEditChain(int $id): void
    {
        $chain = ApprovalChain::findOrFail($id);

        $this->editChainId    = $id;
        $this->chainDeptId    = (string) ($chain->department_id ?? '');
        $this->chainDocType   = $chain->document_type instanceof \BackedEnum ? $chain->document_type->value : (string) $chain->document_type;
        $this->chainLevel     = (string) $chain->level;
        $this->showChainModal = true;
        $this->resetValidation();
    }

    public function closeChainModal(): void
    {
        $this->showChainModal = false;
    }

    public function saveChain(): void
    {
        $this->validate([
            'chainDeptId'  => ['nullable', 'exists:departments,id'],
            'chainDocType' => ['required', 'in:po,mrn,transfer'],
            'chainLevel'   => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $data = [
            'department_id' => $this->chainDeptId ?: null,
            'document_type' => $this->chainDocType,
            'level'         => (int) $this->chainLevel,
        ];

        if ($this->editChainId) {
            ApprovalChain::findOrFail($this->editChainId)->update($data);
            $msg = 'Approval chain level updated.';
        } else {
            ApprovalChain::create($data);
            $msg = 'Approval chain level created.';
        }

        $this->showChainModal = false;
        unset($this->chains);
        $this->dispatch('toast', message: $msg, type: 'success');
    }

    public function deleteChain(int $id): void
    {
        ApprovalChain::findOrFail($id)->delete();
        unset($this->chains);
        $this->dispatch('toast', message: 'Chain level removed.', type: 'info');
    }

    // ─── Users Management ─────────────────────────────────────────────────────

    public function openUsersModal(int $chainId): void
    {
        $this->manageChainId  = $chainId;
        $this->addUserId      = '';
        $this->showUsersModal = true;
        unset($this->availableUsers);
    }

    public function closeUsersModal(): void
    {
        $this->showUsersModal = false;
    }

    public function addUser(): void
    {
        $this->validate(['addUserId' => ['required', 'exists:users,id']]);

        $chain = ApprovalChain::findOrFail($this->manageChainId);
        $chain->chainUsers()->firstOrCreate(['user_id' => (int) $this->addUserId]);

        $this->addUserId = '';
        unset($this->chains);
        $this->dispatch('toast', message: 'User added to chain level.', type: 'success');
    }

    public function removeUser(int $chainUserId): void
    {
        \App\Models\ApprovalChainUser::findOrFail($chainUserId)->delete();
        unset($this->chains);
        $this->dispatch('toast', message: 'User removed.', type: 'info');
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.approval-chains.index')
            ->layout('components.layouts.app', [
                'title'           => 'Approval Chains',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
