<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ContainerForm extends Form
{
    #[Validate('required|string|max:11')]
    public string $containerNumber = '';

    #[Validate('required|string')]
    public string $sealNumber = '';

    #[Validate('required|string')]
    public string $sizeType = '40HC High Cube';

    #[Validate('required|string')]
    public string $blNumber = '';

    public string $vesselName = '';
    public string $voyageNumber = '';
    public string $shippingLine = 'Maersk';

    #[Validate('required|date')]
    public string $etaDate = '';

    public string $portOfDischarge = 'Dar es Salaam Port';
    public string $consigneeId = '';
    public string $yardSlot = '';
    public string $initialStatus = 'Expected';
    public string $remarks = '';

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
