<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class DispatchForm extends Form
{
    #[Validate('required|string')]
    public string $orderId = '';

    public string $gatePassNumber = 'GP-2026-089';

    #[Validate('required')]
    public string $departureTime = '';

    #[Validate('required|string')]
    public string $vehiclePlate = '';

    public string $trailerNumber = '';

    #[Validate('required|string')]
    public string $sealNumber = '';

    #[Validate('required|string')]
    public string $driverName = '';

    #[Validate('required|string')]
    public string $driverPhone = '';

    public string $licenceNumber = '';

    #[Validate('accepted')]
    public bool $checklistLoadVerified = false;

    #[Validate('accepted')]
    public bool $checklistVehicleInspected = false;

    #[Validate('accepted')]
    public bool $checklistSealApplied = false;

    #[Validate('accepted')]
    public bool $checklistDocumentsHandedOver = false;

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
