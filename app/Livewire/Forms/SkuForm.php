<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class SkuForm extends Form
{
    #[Validate('required|string|max:50')]
    public string $skuCode = '';

    public string $barcodeEan = '';
    public string $clientId = '';

    #[Validate('required|string')]
    public string $itemName = '';

    #[Validate('required|string')]
    public string $category = 'Consumer Goods & Food';

    #[Validate('required|string')]
    public string $baseUom = 'Piece';

    public int $unitsPerPallet = 0;
    public string $pickingStrategy = 'FEFO (first expiry, first out)';
    public string $trackExpiry = 'Yes';
    public string $storageCondition = 'Ambient';
    public int $minimumStock = 0;
    public int $reorderPoint = 0;
    public int $maximumStock = 0;

    public function save(): bool
    {
        $this->validate();
        // TODO: persist $this->all()
        return true;
    }
}
