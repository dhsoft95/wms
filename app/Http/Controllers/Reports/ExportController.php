<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportExcelExport;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class ExportController extends Controller
{
    public function __invoke(Request $request, ReportRegistry $registry): Response
    {
        $token = $request->query('token');

        if (! $token) {
            abort(400);
        }

        $payload = Cache::pull("report_export_{$token}");

        if (! $payload) {
            abort(404, 'Export link expired or already used.');
        }

        $definition = $registry->find($payload['key']);

        if ($definition === null) {
            abort(404);
        }

        try {
            activity()
                ->causedBy(auth()->user())
                ->withProperties(['report' => $payload['key'], 'filters' => $payload['filters'], 'format' => $payload['format'], 'rows' => $payload['row_count']])
                ->log('report_exported');
        } catch (\Throwable) {
        }

        $filters = $payload['filters'];
        $user    = auth()->user();

        if ($payload['format'] === 'excel') {
            return app(ReportExcelExport::class)->stream($definition, $filters, $user);
        }

        if ($payload['format'] === 'pdf' && $definition->pdfEnabled()) {
            $rows = $definition->query($filters, $user)->get();
            $view = 'pdf.reports.' . $definition->key();

            $pdf = Pdf::loadView($view, [
                'definition'  => $definition,
                'rows'        => $rows,
                'filters'     => $filters,
                'brandName'   => config('app.name'),
                'generatedAt' => now()->format('d M Y H:i'),
            ])->setPaper([0, 0, 841.89, 595.28], 'landscape');

            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $definition->label() . '.pdf"',
            ]);
        }

        abort(400, 'Unsupported format.');
    }
}
