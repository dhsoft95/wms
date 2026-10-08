<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class AdjustmentForm extends Form
{
    #[Validate('required|string')]
    public string $skuCode = 'SKU-AZ-001';

    #[Validate('required|string')]
    public string $locationBin = 'DAR-A-03-02-B';

    #[Validate('required|string')]
    public string $reasonCode = 'CYCLE_COUNT_VARIANCE';

    public int $systemQuantity = 500;

    #[Validate('required|integer|min:0')]
    public int $physicalCount = 450;

    #[Validate('required|string')]
    public string $justification = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
