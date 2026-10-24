<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Inventory\SerialNumber;
use App\Models\PickList;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PickListDocumentController extends Controller
{
    public function pdf(PickList $pickList): Response
    {
        Gate::authorize('view', $pickList->deliveryOrder);

        $pickList->load([
            'deliveryOrder.consigneePartner',
            'deliveryOrder.warehouse',
            'assignedTo',
            'tasks'               => fn ($q) => $q->orderBy('sequence'),
            'tasks.location',
            'tasks.item.baseUom',
            'tasks.batch',
            'tasks.serials',      // confirmed serials (populated after picking)
        ]);

        // Build serial list per task:
        //   - Picked tasks  → use the confirmed serials from pick_task_serials
        //   - Open tasks    → query what is currently in_stock at the pick location
        $taskSerials = [];
        foreach ($pickList->tasks as $task) {
            if (! $task->item?->track_serial) {
                continue;
            }

            if ($task->serials->isNotEmpty()) {
                $taskSerials[$task->id] = $task->serials->sortBy('serial_no')->pluck('serial_no')->all();
            } else {
                $taskSerials[$task->id] = SerialNumber::where('item_id', $task->item_id)
                    ->where('location_id', $task->location_id)
                    ->where('status', 'in_stock')
                    ->orderBy('serial_no')
                    ->pluck('serial_no')
                    ->all();
            }
        }

        $pdf = Pdf::loadView('pdf.pick-list', [
            'pickList'    => $pickList,
            'taskSerials' => $taskSerials,
            'brand'       => PdfBrand::make(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('PCK-' . $pickList->number . '.pdf');
    }
}
