<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DisposalRequest;
use Illuminate\Contracts\View\View;

class DisposalNoteController extends Controller
{
    public function show(DisposalRequest $disposalRequest): View
    {
        $disposalRequest->load([
            'lines.item.baseUom',
            'lines.batch',
            'lines.location',
            'createdBy',
            'approvedBy',
            'completedBy',
            'virtualWarehouse',
            'department',
        ]);

        return view('inventory.disposals.print-note', compact('disposalRequest'));
    }
}
