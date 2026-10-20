<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class PurchaseOrderDocumentController extends Controller
{
    public function download(PurchaseOrder $purchaseOrder): Response
    {
        abort_if(! $purchaseOrder->po_file_path, 404);

        $path = $purchaseOrder->po_file_path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = $purchaseOrder->number . '-document.' . $ext;
        $mime     = Storage::disk('local')->mimeType($path);

        // Serve inline for browser-renderable types so the PDF viewer iframe can display them
        $inlineTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $inlineTypes, true)) {
            return response()->stream(function () use ($path) {
                echo Storage::disk('local')->get($path);
            }, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Cache-Control'       => 'no-store',
            ]);
        }

        return Storage::disk('local')->download($path, $filename);
    }
}
