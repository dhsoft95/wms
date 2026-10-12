<?php

namespace App\Livewire\Admin\ItemCategories;

use App\Actions\Foundation\SaveItemCategoryAction;
use App\Exceptions\InUseException;
use App\Models\ItemCategory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $search   = '';
    public ?int   $editing  = null;
    public string $name     = '';
    public ?int   $parentId = null;
    public bool   $isActive = true;

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
            ->when($this->editing, fn ($q) => $q->where('id', '!=', $this->editing))
            ->orderBy('name')
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'parentId' => ['nullable', 'exists:item_categories,id'],
            'isActive' => ['boolean'],
        ]);

        if ($this->editing) {
            $category = ItemCategory::findOrFail($this->editing);

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

        $this->resetForm();
        unset($this->categories);
        unset($this->parentOptions);
    }

    public function edit(int $id): void
    {
        $category        = ItemCategory::findOrFail($id);
        $this->editing   = $id;
        $this->name      = $category->name;
        $this->parentId  = $category->parent_id;
        $this->isActive  = (bool) $category->is_active;
        $this->resetValidation();
        unset($this->parentOptions);
    }

    public function cancel(): void
    {
        $this->resetForm();
        unset($this->parentOptions);
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
        unset($this->parentOptions);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->editing  = null;
        $this->name     = '';
        $this->parentId = null;
        $this->isActive = true;
        $this->resetValidation();
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
