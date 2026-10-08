<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class PodForm extends Form
{
    #[Validate('required|string')]
    public string $gatePassId = '';

    #[Validate('required')]
    public string $deliveredAt = '';

    #[Validate('required|string')]
    public string $recipientName = '';

    public string $recipientPhone = '';

    #[Validate('required|in:delivered_full,partial,damaged')]
    public string $deliveryCondition = 'delivered_full';

    public string $remarks = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
