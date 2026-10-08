<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class OrderForm extends Form
{
    public string $orderReference = '';

    #[Validate('required|string')]
    public string $clientId = '';

    #[Validate('required|date')]
    public string $requiredDeliveryDate = '';

    #[Validate('required|string')]
    public string $sourceWarehouse = 'DAR-General Store';

    #[Validate('required|string')]
    public string $deliveryAddress = '';

    public string $contactPerson = '';
    public string $contactPhone = '';
    public string $priority = 'Standard';
    public bool $autoAllocate = true;

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
