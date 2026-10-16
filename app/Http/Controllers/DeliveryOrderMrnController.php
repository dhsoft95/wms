<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeliveryOrderMrnController extends Controller
{
    public function __invoke(DeliveryOrder $deliveryOrder, Request $request): Response
    {
        Gate::authorize('view', $deliveryOrder);

        abort_unless($deliveryOrder->mrn_file_path !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($deliveryOrder->mrn_file_path), 404);

        $ext         = strtolower(pathinfo($deliveryOrder->mrn_file_path, PATHINFO_EXTENSION));
        $mime        = match($ext) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            default       => 'application/octet-stream',
        };
        $isViewable  = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $disposition = ($isViewable && ! $request->boolean('download')) ? 'inline' : 'attachment';
        $filename    = 'MRN-' . ($deliveryOrder->mrn_number ?? $deliveryOrder->number) . '.' . $ext;

        return response($disk->get($deliveryOrder->mrn_file_path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }
}
