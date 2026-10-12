<?php

namespace App\Livewire\Admin\DataImport;

use App\Enums\ImportMode;
use App\Enums\ImportType;
use App\Jobs\ValidateImportJob;
use App\Models\DataImport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public ?string $uploadType = null;
    public $uploadFile = null;
    public string $uploadMode = 'create_only';
    public bool   $autoCategory = false;

    public bool $uploadModalOpen = false;
    public bool $guideModalOpen  = false;

    // History filters
    public ?string $filterType   = '';
    public ?string $filterStatus = '';

    public function openUpload(string $type): void
    {
        $this->authorize('imports.manage');
        $this->uploadType        = $type;
        $this->uploadFile        = null;
        $this->uploadMode        = 'create_only';
        $this->uploadModalOpen   = true;
    }

    public function closeUpload(): void
    {
        $this->uploadModalOpen = false;
        $this->uploadFile      = null;
        $this->autoCategory    = false;
    }

    public function openGuide(): void
    {
        $this->guideModalOpen = true;
    }

    public function closeGuide(): void
    {
        $this->guideModalOpen = false;
    }

    public function upload(): void
    {
        $this->authorize('imports.manage');

        $this->validate([
            'uploadFile' => 'required|file|mimes:xlsx,csv,xls|max:20480',
            'uploadMode' => 'required|in:create_only,create_or_update',
        ]);

        $importType = ImportType::from($this->uploadType);

        // Extra gate for opening stock
        if ($importType === ImportType::OpeningStock) {
            $this->authorize('stock.opening-balance');
        }

        // System-admin-only types
        if ($importType->restrictedToSystemAdmin() && ! auth()->user()->hasRole('system-admin')) {
            abort(403);
        }

        $stored = $this->uploadFile->storeAs(
            'imports',
            now()->format('Ymd_His') . '_' . $importType->value . '.' . $this->uploadFile->getClientOriginalExtension(),
            'local'
        );

        $import = DataImport::create([
            'type'              => $importType,
            'original_filename' => $this->uploadFile->getClientOriginalName(),
            'stored_path'       => $stored,
            'mode'              => ImportMode::from($this->uploadMode),
            'uploaded_by'       => auth()->id(),
            'status'            => 'uploaded',
        ]);

        ValidateImportJob::dispatch($import);

        $this->closeUpload();
        $this->dispatch('toast', message: 'File uploaded. Validation is running…', type: 'info');
        $this->redirect(route('admin.imports.show', $import), navigate: true);
    }

    #[Computed]
    public function importTypes(): array
    {
        return ImportType::cases();
    }

    #[Computed]
    public function lastImports(): array
    {
        $lastIds = DataImport::selectRaw('type, MAX(id) as last_id')
            ->groupBy('type')
            ->pluck('last_id', 'type');

        if ($lastIds->isEmpty()) {
            return array_fill_keys(array_map(fn ($t) => $t->value, ImportType::cases()), null);
        }

        $imports = DataImport::whereIn('id', $lastIds->values())
            ->get()
            ->keyBy(fn ($m) => $m->type->value);

        $result = [];
        foreach (ImportType::cases() as $type) {
            $result[$type->value] = $imports->get($type->value);
        }

        return $result;
    }

    #[Computed]
    public function history()
    {
        $query = DataImport::with('uploadedBy')->orderByDesc('id');

        if ($this->filterType !== '') {
            $query->where('type', $this->filterType);
        }
        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return $query->paginate(20);
    }

    public function hasInProgressRows(): bool
    {
        return DataImport::whereIn('status', ['uploaded', 'validating', 'importing'])->exists();
    }

    public function render(): View
    {
        return view('livewire.admin.data-import.index')
            ->layout('components.layouts.app', [
                'title'           => 'Data Import',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
