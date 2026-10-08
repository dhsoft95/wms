<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class UserForm extends Form
{
    #[Validate('required|string')]
    public string $fullName = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $role = 'Storekeeper';

    #[Validate('required|string')]
    public string $warehouseScope = 'DAR-General Store';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
