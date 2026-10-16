<?php

declare(strict_types=1);

namespace App\Livewire\Scan;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
use App\Services\Outbound\DispatchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dispatch extends Component
{
    public string  $step       = 'scan_do';  // scan_do | fill_details | done
    public string  $doNumber   = '';
    public ?int    $doId       = null;
    public string  $scanError  = '';

    // dispatch details
    public string $vehicleNo   = '';
    public string $driverName  = '';
    public string $driverPhone = '';
    public string $sealNo      = '';

    public ?string $dispatchNumber  = null;
    public ?string $gatePassNumber  = null;

    public function scanDo(): void
    {
        $this->scanError = '';

        $do = DeliveryOrder::where('number', strtoupper(trim($this->doNumber)))->first();

        if ($do === null) {
            $this->scanError = 'Delivery order not found.';
            return;
        }

        if ($do->status !== DeliveryOrderStatus::Picked) {
            $this->scanError = "Order {$do->number} is not ready for dispatch (status: {$do->status->label()}).";
            return;
        }

        $this->doId  = $do->id;
        $this->step  = 'fill_details';
    }

    public function confirmDispatch(): void
    {
        $this->validate([
            'vehicleNo'  => ['required', 'string', 'max:20'],
            'driverName' => ['required', 'string', 'max:100'],
        ]);

        $do = DeliveryOrder::findOrFail($this->doId);

        try {
            $dispatch = app(DispatchService::class)->confirmDispatch(
                $do,
                [
                    'vehicle_no'   => $this->vehicleNo,
                    'driver_name'  => $this->driverName,
                    'driver_phone' => $this->driverPhone ?: null,
                    'seal_no'      => $this->sealNo ?: null,
                ],
                auth()->user(),
                "scan:dispatch:{$do->id}"
            );
        } catch (\DomainException $e) {
            $this->addError('vehicleNo', $e->getMessage());
            return;
        }

        $this->dispatchNumber = $dispatch->number;
        $this->gatePassNumber = $dispatch->gatePass?->number;
        $this->step           = 'done';
    }

    public function startOver(): void
    {
        $this->step           = 'scan_do';
        $this->doNumber       = '';
        $this->doId           = null;
        $this->scanError      = '';
        $this->vehicleNo      = '';
        $this->driverName     = '';
        $this->driverPhone    = '';
        $this->sealNo         = '';
        $this->dispatchNumber = null;
        $this->gatePassNumber = null;
    }

    public function render(): View
    {
        return view('livewire.scan.dispatch')
            ->layout('components.layouts.scan', ['title' => 'Dispatch']);
    }
}
