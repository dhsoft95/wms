<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class InspectionForm extends Form
{
    #[Validate('required|string')]
    public string $grnId = '';

    public string $container = '';

    #[Validate('required|string')]
    public string $issueType = 'Shortage (missing qty)';

    #[Validate('required|string')]
    public string $affectedSku = '';

    #[Validate('required|integer|min:1')]
    public int $affectedQuantity = 1;

    #[Validate('required|in:Low,Medium,High')]
    public string $severity = 'Medium';

    public string $remarks = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
