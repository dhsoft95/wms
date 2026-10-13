<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ContainerDocumentController extends Controller
{
    public function __invoke(Container $container, ContainerDocument $document, Request $request): Response
    {
        Gate::authorize('view', $document);

        abort_unless($document->container_id === $container->id, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->path), 404);

        $isViewable  = str_starts_with($document->mime, 'image/') || $document->mime === 'application/pdf';
        $disposition = ($isViewable && !$request->boolean('download')) ? 'inline' : 'attachment';

        return response($disk->get($document->path), 200, [
            'Content-Type'        => $document->mime,
            'Content-Disposition' => $disposition . '; filename="' . $document->original_name . '"',
        ]);
    }
}
