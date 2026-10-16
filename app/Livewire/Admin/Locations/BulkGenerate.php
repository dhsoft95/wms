<?php

namespace App\Livewire\Admin\Locations;

use App\Actions\Foundation\BulkGenerateLocationsAction;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BulkGenerate extends Component
{
    public string $warehouseId      = '';
    public string $parentId         = '';
    public string $level            = 'bin';
    public string $type             = 'storage';
    public string $segmentPrefix    = '';
    public string $segmentStart     = '';
    public string $segmentEnd       = '';
    public string $segmentPadding   = '2';
    public bool   $previewing       = false;

    /** @var array<int, array{code: string, segment: string, exists: bool}> */
    public array $previewList = [];

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::active()->orderBy('code')->get();
    }

    #[Computed]
    public function parentOptions(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->warehouseId) {
            return collect();
        }

        return Location::where('warehouse_id', $this->warehouseId)
            ->orderBy('code')
            ->get();
    }

    // ─── Updaters ─────────────────────────────────────────────────────────────

    public function updatedWarehouseId(): void
    {
        $this->parentId   = '';
        $this->previewing = false;
        $this->previewList = [];
    }

    public function updatedParentId(): void
    {
        $this->previewing = false;
        $this->previewList = [];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generate a list of segment code strings between $start and $end.
     * Supports numeric ranges (01..20) and single-char alpha ranges (A..Z).
     *
     * @return array<string>
     */
    private function generateSegments(string $start, string $end, int $padding, string $prefix): array
    {
        $segments = [];

        if (is_numeric($start) && is_numeric($end)) {
            $from = (int) $start;
            $to   = (int) $end;

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            for ($i = $from; $i <= $to; $i++) {
                $segments[] = $prefix . str_pad((string) $i, $padding, '0', STR_PAD_LEFT);
            }
        } else {
            // Single-character alphabetic range
            $from = strtoupper(substr($start, 0, 1));
            $to   = strtoupper(substr($end, 0, 1));

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            for ($char = $from; $char <= $to; $char++) {
                $segments[] = $prefix . $char;
                if ($char === 'Z') {
                    break; // guard
                }
                $char = chr(ord($char) + 1);
                $char = chr(ord($char) - 1); // let for loop handle increment
            }

            // Re-do with chr iteration for clarity
            $segments = [];
            $fromOrd  = ord($from);
            $toOrd    = ord($to);
            for ($o = $fromOrd; $o <= $toOrd; $o++) {
                $segments[] = $prefix . chr($o);
            }
        }

        return $segments;
    }

    private function buildCodePrefix(): string
    {
        if ($this->parentId) {
            $parent = Location::find($this->parentId);
            if ($parent) {
                return $parent->code . '-';
            }
        }

        $warehouse = Warehouse::find($this->warehouseId);
        return $warehouse ? $warehouse->code . '-' : '';
    }

    private function buildPath(): string
    {
        if ($this->parentId) {
            $parent = Location::find($this->parentId);
            if ($parent) {
                return $parent->path . $parent->id . '/';
            }
        }

        return '/';
    }

    // ─── Preview ──────────────────────────────────────────────────────────────

    public function preview(): void
    {
        $isBin = $this->level === 'bin';

        $this->validate([
            'warehouseId'  => ['required', 'exists:warehouses,id'],
            'level'        => ['required', 'in:zone,aisle,rack,shelf_level,bin'],
            'type'         => $isBin ? ['required', 'in:yard_slot,receiving_dock,storage,staging,quarantine'] : [],
            'segmentStart' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'segmentEnd'   => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'segmentPadding' => ['nullable', 'integer', 'min:1', 'max:6'],
        ]);

        if (! $isBin) {
            $this->type = 'storage';
        }

        $padding  = (int) ($this->segmentPadding ?: 2);
        $prefix   = strtoupper($this->segmentPrefix);
        $segments = $this->generateSegments($this->segmentStart, $this->segmentEnd, $padding, $prefix);
        $codePrefix = $this->buildCodePrefix();

        // Build full codes
        $codes = array_map(fn ($seg) => $codePrefix . $seg, $segments);

        // Find which already exist, including soft-deleted (the DB unique index covers them)
        $existing = Location::withTrashed()->whereIn('code', $codes)->pluck('code')->flip();

        $this->previewList = array_map(function ($code, $seg) use ($existing) {
            return [
                'code'    => $code,
                'segment' => $seg,
                'exists'  => $existing->has($code),
            ];
        }, $codes, $segments);

        $this->previewing = true;
    }

    // ─── Generate ─────────────────────────────────────────────────────────────

    public function generate(): void
    {
        if (! $this->previewing || empty($this->previewList)) {
            return;
        }

        $result = BulkGenerateLocationsAction::execute(
            warehouseId: $this->warehouseId,
            parentId: $this->parentId ?: null,
            level: $this->level,
            type: $this->type,
            previewList: $this->previewList,
        );

        $this->dispatch(
            'toast',
            message: "{$result['created']} location(s) created, {$result['skipped']} skipped (already exist).",
            type: 'success'
        );

        $this->redirect(route('admin.locations.index'), navigate: true);
    }

    public function backToForm(): void
    {
        $this->previewing  = false;
        $this->previewList = [];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.locations.bulk-generate')
            ->layout('components.layouts.app', [
                'title'           => 'Bulk Generate Locations',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
