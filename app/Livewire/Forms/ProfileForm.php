<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ProfileForm extends Form
{
    #[Validate('required|string')]
    public string $fullName = '';

    #[Validate('required|email')]
    public string $email = '';

    public string $phone = '';
    public string $language = 'English';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
