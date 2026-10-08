<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class MovementForm extends Form
{
    #[Validate('required|string')]
    public string $skuCode = '';

    #[Validate('required|integer|min:1')]
    public int $quantity = 1;

    #[Validate('required|string')]
    public string $sourceBin = '';

    #[Validate('required|string')]
    public string $targetBin = '';

    #[Validate('required|string')]
    public string $movementReason = 'Replenishment to pick face';

    public string $notes = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
