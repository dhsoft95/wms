<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ContainerPoController extends Controller
{
    public function __invoke(Container $container, Request $request): Response
    {
        Gate::authorize('view', $container);

        abort_unless($container->po_file_path !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($container->po_file_path), 404);

        $ext         = strtolower(pathinfo($container->po_file_path, PATHINFO_EXTENSION));
        $mime        = match($ext) {
            'pdf'        => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            default      => 'application/octet-stream',
        };
        $isViewable  = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $disposition = ($isViewable && ! $request->boolean('download')) ? 'inline' : 'attachment';
        $filename    = 'PO-' . ($container->po_number ?? $container->number) . '.' . $ext;

        return response($disk->get($container->po_file_path), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }
}
