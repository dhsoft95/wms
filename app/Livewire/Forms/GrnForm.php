<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class GrnForm extends Form
{
    public string $grnNumber = '';

    #[Validate('required|string')]
    public string $containerId = '';

    #[Validate('required|string')]
    public string $supplierId = '';

    #[Validate('required|string')]
    public string $receivingWarehouse = '';

    #[Validate('required')]
    public string $receivedAt = '';

    public string $deliveryNoteRef = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
