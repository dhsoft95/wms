<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Container;
use App\Services\Reports\ContainerReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ContainerReportController extends Controller
{
    public function __invoke(Container $container): Response
    {
        Gate::authorize('view', $container);

        $data = app(ContainerReportService::class)->build($container);

        $pdf = Pdf::loadView('reports.container.pdf', $data)
            ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => false])
            ->setPaper([0, 0, 841.89, 595.28], 'landscape');

        $slug = preg_replace('/[^A-Za-z0-9\-]/', '-', $container->container_no ?? $container->number);

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="container-report-' . $slug . '.pdf"',
        ]);
    }
}
