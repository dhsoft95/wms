<?php

namespace App\Livewire\Forms;

use App\Models\Department;
use Livewire\Form;

class DepartmentForm extends Form
{
    public string $name        = '';
    public string $code        = '';
    public string $headUserId  = '';
    public string $reportEmail = '';
    public bool   $isActive    = true;

    public function fillFrom(Department $dept): void
    {
        $this->name        = $dept->name;
        $this->code        = $dept->code;
        $this->headUserId  = (string) ($dept->head_user_id ?? '');
        $this->reportEmail = $dept->report_email ?? '';
        $this->isActive    = (bool) $dept->is_active;
    }

    public function toSave(): array
    {
        return [
            'name'         => $this->name,
            'code'         => strtoupper($this->code),
            'head_user_id' => $this->headUserId ?: null,
            'report_email' => $this->reportEmail ?: null,
            'is_active'    => $this->isActive,
        ];
    }
}
