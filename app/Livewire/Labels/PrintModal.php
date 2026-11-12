<?php

declare(strict_types=1);

namespace App\Livewire\Labels;

use App\Enums\LabelTemplateType;
use App\Enums\LocationLevel;
use App\Models\Container;
use App\Models\Dispatch;
use App\Models\Inventory\Lpn;
use App\Models\Item;
use App\Models\LabelTemplate;
use App\Models\Location;
use App\Models\Printer;
use App\Models\ReasonCode;
use App\Services\Labels\PrintJobService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PrintModal extends Component
{
    public bool   $show          = false;
    public string $type          = '';       // LabelTemplateType value
    public array  $ids           = [];
    public string $templateId    = '';
    public string $printerId     = '';
    public int    $copies        = 1;
    public string $reasonCodeId  = '';
    public string $reprintNote   = '';
    public string $locationLevel = '';     // LocationLevel value, used to filter Location templates

    // ─── Open ─────────────────────────────────────────────────────────────────

    #[On('open-label-print')]
    public function open(string $type, array $ids, int $copies = 1, string $locationLevel = ''): void
    {
        $this->type          = $type;
        $this->ids           = $ids;
        $this->copies        = max(1, $copies);
        $this->templateId    = '';
        $this->printerId     = '';
        $this->reasonCodeId  = '';
        $this->reprintNote   = '';
        $this->locationLevel = $locationLevel;

        // Default template: prefer level-matched default, then any level-matched, then global default
        $base = LabelTemplate::where('type', $type)->where('is_active', true);

        $tpl = (clone $base)->where('is_default', true)->forLevel($locationLevel ?: null)->first()
            ?? (clone $base)->forLevel($locationLevel ?: null)->first()
            ?? (clone $base)->where('is_default', true)->first()
            ?? (clone $base)->first();

        if ($tpl) {
            $this->templateId = (string) $tpl->id;
        }

        // Default printer is set from localStorage in the view via Alpine; fall back to first active
        if ($this->printerId === '') {
            $printer = Printer::active()->orderBy('name')->first();
            if ($printer) {
                $this->printerId = (string) $printer->id;
            }
        }

        unset($this->templates, $this->printers, $this->printables, $this->reprintReasonCodes);

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->ids  = [];
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function templates(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->type === '') return collect();

        return LabelTemplate::where('type', $this->type)
            ->where('is_active', true)
            ->forLevel($this->locationLevel ?: null)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function locationLevelLabel(): string
    {
        if ($this->locationLevel === '') return '';
        $level = LocationLevel::tryFrom($this->locationLevel);
        return $level?->label() ?? '';
    }

    #[Computed]
    public function printers(): \Illuminate\Database\Eloquent\Collection
    {
        return Printer::active()->orderBy('name')->get();
    }

    #[Computed]
    public function printables(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->ids) || $this->type === '') return collect();

        $modelClass = $this->modelClassForType($this->type);
        if (! $modelClass) return collect();

        return $modelClass::whereIn('id', $this->ids)->get();
    }

    #[Computed]
    public function needsReprintReason(): bool
    {
        if ($this->type !== LabelTemplateType::PalletLpn->value) return false;

        return $this->printables->contains(fn ($p) => $p instanceof Lpn && $p->print_count > 0);
    }

    #[Computed]
    public function reprintReasonCodes(): \Illuminate\Database\Eloquent\Collection
    {
        return ReasonCode::where('applies_to', 'reprint')->where('is_active', true)->orderBy('name')->get();
    }

    // ─── Confirm ──────────────────────────────────────────────────────────────

    public function confirmPrint(): void
    {
        $this->validate([
            'templateId' => ['required', 'exists:label_templates,id'],
            'printerId'  => ['required', 'exists:printers,id'],
            'copies'     => ['required', 'integer', 'min:1', 'max:99'],
            'reasonCodeId' => $this->needsReprintReason ? ['required'] : ['nullable'],
        ]);

        $type    = LabelTemplateType::from($this->type);
        $tpl     = LabelTemplate::findOrFail($this->templateId);
        $printer = Printer::findOrFail($this->printerId);

        try {
            $result = app(PrintJobService::class)->print(
                type:         $type,
                printables:   $this->printables,
                printer:      $printer,
                copies:       $this->copies,
                user:         auth()->user(),
                tpl:          $tpl,
                reasonCodeId: $this->reasonCodeId ?: null,
                note:         $this->reprintNote ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('reasonCodeId', $e->getMessage());
            return;
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->dispatch('toast', message: 'You are not authorised to reprint labels.', type: 'error');
            return;
        }

        $this->show = false;

        match ($result->outputType) {
            'pdf'  => $this->dispatch('label-print-pdf',  url: route('labels.pdf', $result->pdfToken)),
            'sent' => $this->dispatch('toast', message: "Sent to {$result->printerName}.", type: 'success'),
            'zpl'  => $this->dispatch('label-print-zpl',  content: base64_encode($result->content), filename: 'labels.zpl'),
            default => null,
        };
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function modelClassForType(string $type): ?string
    {
        return match($type) {
            LabelTemplateType::PalletLpn->value  => Lpn::class,
            LabelTemplateType::Location->value   => Location::class,
            LabelTemplateType::Item->value       => Item::class,
            LabelTemplateType::Container->value  => Container::class,
            LabelTemplateType::Dispatch->value   => Dispatch::class,
            default                              => null,
        };
    }

    public function render(): View
    {
        return view('livewire.labels.print-modal');
    }
}
