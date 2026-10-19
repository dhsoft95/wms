<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderDocumentController extends Controller
{
    public function download(PurchaseOrder $purchaseOrder): StreamedResponse
    {
        abort_if(! $purchaseOrder->po_file_path, 404);

        $path = $purchaseOrder->po_file_path;

        abort_unless(Storage::disk('local')->exists($path), 404);

        $ext      = pathinfo($path, PATHINFO_EXTENSION);
        $filename = $purchaseOrder->number . '-document.' . $ext;

        // Serve inline for browser-renderable types (PDF, images) so the iframe can display them
        $inlineTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $inlineTypes)) {
            $mime = Storage::disk('local')->mimeType($path);
            return response()->streamDownload(function () use ($path) {
                echo Storage::disk('local')->get($path);
            }, $filename, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return Storage::disk('local')->download($path, $filename);
    }
}
