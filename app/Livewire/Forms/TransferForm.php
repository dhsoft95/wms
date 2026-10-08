<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class TransferForm extends Form
{
    #[Validate('required|string')]
    public string $sourceDepartment = 'DAR-General Store';

    #[Validate('required|string')]
    public string $targetDepartment = 'Zanzibar Regional Hub';

    #[Validate('required|string')]
    public string $skuCode = '';

    #[Validate('required|integer|min:1')]
    public int $quantity = 1;

    #[Validate('required|date')]
    public string $requiredBy = '';

    public string $transportMode = 'Road freight';

    #[Validate('required|string')]
    public string $transferReason = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
