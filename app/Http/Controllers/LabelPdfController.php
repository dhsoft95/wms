<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class LabelPdfController extends Controller
{
    public function __invoke(string $token): Response
    {
        $cached = Cache::get("label_pdf:{$token}");

        abort_if($cached === null, 404, 'Label PDF expired or not found.');

        $pdf = base64_decode($cached, strict: true) ?: $cached;

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label.pdf"',
        ]);
    }
}
