<?php

namespace App\Livewire\Admin\ItemCategories;

use App\Actions\Foundation\SaveItemCategoryAction;
use App\Exceptions\InUseException;
use App\Models\ItemCategory;
use App\Models\ItemCategoryUser;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    // ─── Search ───────────────────────────────────────────────────────────────

    public string $search = '';

    // ─── Category modal ───────────────────────────────────────────────────────

    public bool    $showCategoryModal = false;
    public ?int    $editingId         = null;
    public string  $name              = '';
    public ?int    $parentId          = null;
    public bool    $isActive          = true;

    // ─── Users modal ──────────────────────────────────────────────────────────

    public bool $showUsersModal   = false;
    public ?int $managingUsersFor = null;
    public ?int $addUserId        = null;
    public ?int $addWarehouseId   = null;

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return ItemCategory::with('parent')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function parentOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return ItemCategory::active()
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function managedCategory(): ?ItemCategory
    {
        return $this->managingUsersFor ? ItemCategory::find($this->managingUsersFor) : null;
    }

    #[Computed]
    public function assignments(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->managingUsersFor) {
            return collect();
        }

        return ItemCategoryUser::with(['user', 'warehouse'])
            ->where('item_category_id', $this->managingUsersFor)
            ->orderBy('warehouse_id')
            ->get();
    }

    #[Computed]
    public function userOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function warehouseOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::orderBy('name')->get(['id', 'name', 'code']);
    }

    // ─── Category modal actions ───────────────────────────────────────────────

    public function openCreateCategory(): void
    {
        $this->editingId         = null;
        $this->name              = '';
        $this->parentId          = null;
        $this->isActive          = true;
        $this->showCategoryModal = true;
        $this->resetValidation();
        unset($this->parentOptions);
    }

    public function openEditCategory(int $id): void
    {
        $category                = ItemCategory::findOrFail($id);
        $this->editingId         = $id;
        $this->name              = $category->name;
        $this->parentId          = $category->parent_id;
        $this->isActive          = (bool) $category->is_active;
        $this->showCategoryModal = true;
        $this->resetValidation();
        unset($this->parentOptions);
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->editingId         = null;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'parentId' => ['nullable', 'exists:item_categories,id'],
            'isActive' => ['boolean'],
        ]);

        if ($this->editingId) {
            $category = ItemCategory::findOrFail($this->editingId);

            try {
                SaveItemCategoryAction::update($category, [
                    'name'      => $this->name,
                    'parent_id' => $this->parentId,
                    'is_active' => $this->isActive,
                ]);
            } catch (InUseException) {
                $this->addError('isActive', 'Cannot deactivate: this category has items assigned to it.');
                return;
            }

            $this->dispatch('toast', message: "Category \"{$this->name}\" updated.", type: 'success');
        } else {
            SaveItemCategoryAction::create([
                'name'      => $this->name,
                'parent_id' => $this->parentId,
                'is_active' => $this->isActive,
            ]);
            $this->dispatch('toast', message: "Category \"{$this->name}\" created.", type: 'success');
        }

        $this->closeCategoryModal();
        unset($this->categories);
    }

    public function toggleActive(int $id): void
    {
        $category = ItemCategory::findOrFail($id);

        try {
            $state = SaveItemCategoryAction::toggle($category);
        } catch (InUseException) {
            $this->dispatch('toast', message: "Cannot deactivate \"{$category->name}\": it has items assigned to it.", type: 'error');
            return;
        }

        $this->dispatch('toast', message: "\"{$category->name}\" {$state}.", type: 'info');
        unset($this->categories);
    }

    // ─── Users modal actions ──────────────────────────────────────────────────

    public function openUsersModal(int $id): void
    {
        $this->managingUsersFor = $id;
        $this->addUserId        = null;
        $this->addWarehouseId   = null;
        $this->showUsersModal   = true;
        $this->resetValidation();
        unset($this->managedCategory, $this->assignments);
    }

    public function closeUsersModal(): void
    {
        $this->showUsersModal   = false;
        $this->managingUsersFor = null;
        $this->addUserId        = null;
        $this->addWarehouseId   = null;
        $this->resetValidation();
    }

    public function addUser(): void
    {
        $this->validate([
            'addUserId'      => ['required', 'exists:users,id'],
            'addWarehouseId' => ['required', 'exists:warehouses,id'],
        ], [
            'addUserId.required'      => 'Please select a user.',
            'addWarehouseId.required' => 'Please select a warehouse.',
        ]);

        $exists = ItemCategoryUser::where('item_category_id', $this->managingUsersFor)
            ->where('user_id', $this->addUserId)
            ->where('warehouse_id', $this->addWarehouseId)
            ->exists();

        if ($exists) {
            $this->addError('addUserId', 'This user is already assigned to this category and warehouse.');
            return;
        }

        ItemCategoryUser::create([
            'item_category_id' => $this->managingUsersFor,
            'user_id'          => $this->addUserId,
            'warehouse_id'     => $this->addWarehouseId,
        ]);

        $this->addUserId      = null;
        $this->addWarehouseId = null;
        $this->resetValidation();
        unset($this->assignments);

        $this->dispatch('toast', message: 'User assigned.', type: 'success');
    }

    public function removeUser(int $assignmentId): void
    {
        ItemCategoryUser::findOrFail($assignmentId)->delete();
        unset($this->assignments);
        $this->dispatch('toast', message: 'Assignment removed.', type: 'info');
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.item-categories.index')
            ->layout('components.layouts.app', [
                'title'           => 'Item Categories',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
