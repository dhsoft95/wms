<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\OdnStatus;
use App\Models\Dispatch;
use App\Models\OutboundDeliveryNote;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;

final class OdnService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Create an ODN record + lines from a dispatched Dispatch.
     * Must be called inside an existing DB transaction.
     */
    public function createFromDispatch(Dispatch $dispatch, User $by): OutboundDeliveryNote
    {
        // Load all needed relationships
        $dispatch->load([
            'deliveryOrder.lines.item',
            'deliveryOrder.lines.uom',
            'deliveryOrder.lines.allocations.batch',
            'deliveryOrder.lines.allocations.pickTask.serials',
            'deliveryOrder.warehouse',
            'transporterPartner',
        ]);

        $do = $dispatch->deliveryOrder;

        $odn = OutboundDeliveryNote::create([
            'number'            => DocumentNumber::next('ODN'),
            'dispatch_id'       => $dispatch->id,
            'delivery_order_id' => $do?->id,
            'status'            => OdnStatus::Generated,
            'from_warehouse_id' => $do?->warehouse_id,
            'vehicle_reg_no'    => $dispatch->vehicle_reg_no,
            'container_no'      => $dispatch->container_no,
            'container_size'    => $dispatch->container_size?->value,
            'seal_no'           => $dispatch->seal_no,
            'driver_name'       => $dispatch->driver_name,
            'driver_phone'      => $dispatch->driver_phone,
            'transporter_name'  => $dispatch->transporterPartner?->name,
            'remarks'           => $dispatch->notes,
            'generated_by'      => $by->id,
            'generated_at'      => now(),
            'printed_count'     => 0,
        ]);

        // Build ODN lines from DO lines
        if ($do !== null) {
            $lineNo = 1;
            foreach ($do->lines as $line) {
                // Collect picked allocations for this line
                $pickedAllocations = $line->allocations->filter(
                    fn ($a) => $a->status === AllocationStatus::Picked
                );

                // Aggregate serial numbers from all picked tasks on this line
                $serials = $pickedAllocations
                    ->flatMap(fn ($a) => $a->pickTask?->serials ?? collect())
                    ->pluck('serial_no')
                    ->filter()
                    ->values()
                    ->toArray();

                // Use batch info from first picked allocation
                $batch      = $pickedAllocations->first()?->batch;
                $batchNo    = $batch?->batch_no;
                $expiryDate = $batch?->expiry_date;

                // Dispatched qty for this line
                $qty = (float) ($line->dispatched_qty ?? $line->picked_qty ?? 0);

                $odn->lines()->create([
                    'do_line_id'     => $line->id,
                    'line_no'        => $lineNo++,
                    'sku'            => $line->item?->sku,
                    'description'    => $line->item?->name,
                    'batch_no'       => $batchNo,
                    'expiry_date'    => $expiryDate,
                    'qty'            => $qty,
                    'uom_code'       => $line->uom?->code,
                    'serial_numbers' => ! empty($serials) ? $serials : null,
                ]);
            }
        }

        return $odn;
    }

    /**
     * Generate a PDF for the given ODN.
     * Increments the printed_count each time.
     */
    public function generatePdf(OutboundDeliveryNote $odn): \Barryvdh\DomPDF\PDF
    {
        $odn->load([
            'lines',
            'dispatch.dispatchedBy',
            'dispatch.gatePass',
            'deliveryOrder.consigneePartner',
            'fromWarehouse',
            'generatedBy',
        ]);

        // Increment print count
        $odn->increment('printed_count');
        $odn->refresh();

        // Company settings
        $companyName    = SettingsService::get('company.name', config('app.name'));
        $companyAddress = SettingsService::get('company.address', '');
        $companyPhone   = SettingsService::get('company.phone', '');
        $companyEmail   = SettingsService::get('company.email', '');
        $companyTin     = SettingsService::get('company.tin', '');
        $logoPath       = SettingsService::get('company.logo_path');
        $brandName      = SettingsService::get('brand.name', config('app.name'));

        // Resolve logo as base64 inline image for DomPDF
        $logoData = null;
        if ($logoPath && file_exists(storage_path('app/public/' . ltrim($logoPath, '/')))) {
            $absolutePath = storage_path('app/public/' . ltrim($logoPath, '/'));
            $mimeType     = mime_content_type($absolutePath) ?: 'image/png';
            $logoData     = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absolutePath));
        }

        // Attempt barcode generation
        $barcodeData = null;
        try {
            if (class_exists(\Com\Tecnick\Barcode\Barcode::class)) {
                $barcodeObj  = new \Com\Tecnick\Barcode\Barcode();
                $barcode     = $barcodeObj->getBarcodeObj('C128B', $odn->number, -2, -30, 'black', [0, 0, 0, 0]);
                $barcodeData = 'data:image/png;base64,' . base64_encode($barcode->getPngData(true));
            }
        } catch (\Throwable) {
            // Barcode generation is optional — silently skip on failure
        }

        $data = [
            'odn'            => $odn,
            'brandName'      => $brandName,
            'companyName'    => $companyName,
            'companyAddress' => $companyAddress,
            'companyPhone'   => $companyPhone,
            'companyEmail'   => $companyEmail,
            'companyTin'     => $companyTin,
            'logoData'       => $logoData,
            'barcodeData'    => $barcodeData,
        ];

        return Pdf::loadView('pdf.odn', $data)
            ->setPaper([0, 0, 595.28, 841.89], 'portrait');
    }
}
