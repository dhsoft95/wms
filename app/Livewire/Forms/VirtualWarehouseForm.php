<?php

namespace App\Livewire\Forms;

use App\Models\VirtualWarehouse;
use Livewire\Form;

class VirtualWarehouseForm extends Form
{
    public string $name     = '';
    public string $code     = '';
    public bool   $isActive = true;

    public function fillFrom(VirtualWarehouse $vw): void
    {
        $this->name     = $vw->name;
        $this->code     = $vw->code;
        $this->isActive = (bool) $vw->is_active;
    }

    public function toSave(): array
    {
        return [
            'name'      => $this->name,
            'code'      => strtoupper($this->code),
            'is_active' => $this->isActive,
        ];
    }
}
