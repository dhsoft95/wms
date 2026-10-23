<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Inventory\MovementType;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\ReportRequest;
use App\Services\Foundation\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPdfController extends Controller
{
    private function reportDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('reports.disk', 'local'));
    }

    public function download(ReportRequest $reportRequest): StreamedResponse
    {
        abort_unless($reportRequest->isReady() && $reportRequest->file_path, 404);
        abort_unless($this->reportDisk()->exists($reportRequest->file_path), 404);

        $filename = basename($reportRequest->file_path);
        return $this->reportDisk()->download($reportRequest->file_path, $filename);
    }

    public function view(ReportRequest $reportRequest): StreamedResponse
    {
        abort_unless($reportRequest->isReady() && $reportRequest->file_path, 404);
        abort_unless($this->reportDisk()->exists($reportRequest->file_path), 404);

        $contents = $this->reportDisk()->get($reportRequest->file_path);
        $filename = basename($reportRequest->file_path);

        return response()->stream(
            fn () => print($contents),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    private function dates(Request $request): array
    {
        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->query('to',   now()->format('Y-m-d'));
        return [$from, $to];
    }

    private function stream(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper([0, 0, 595.28, 841.89], 'portrait');
        return $pdf->stream($filename . '.pdf');
    }

    public function stockValuation(Request $request): Response
    {
        $balances = StockBalance::with(['item.baseUom', 'location', 'batch'])
            ->where('qty_on_hand', '>', 0)
            ->orderBy('item_id')
            ->orderBy('location_id')
            ->get();

        return $this->stream('pdf.reports.stock-valuation', [
            'brandName' => SettingsService::get('company.name', config('app.name')),
            'balances'  => $balances,
            'asOf'      => now()->format('d M Y H:i'),
        ], 'stock-valuation-' . now()->format('Ymd'));
    }

    public function receivingSummary(Request $request): Response
    {
        [$from, $to] = $this->dates($request);

        $grns = Grn::with(['purchaseOrder.supplierPartner', 'warehouse', 'lines.item', 'lines.uom', 'confirmedBy'])
            ->whereNotNull('confirmed_at')
            ->whereBetween('confirmed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('confirmed_at')
            ->get();

        return $this->stream('pdf.reports.receiving-summary', [
            'brandName' => SettingsService::get('company.name', config('app.name')),
            'grns'      => $grns,
            'from'      => $from,
            'to'        => $to,
        ], 'receiving-summary-' . $from . '-' . $to);
    }

    public function cycleCountVariances(Request $request): Response
    {
        [$from, $to] = $this->dates($request);

        $lines = CycleCountLine::with(['count', 'item', 'location'])
            ->whereHas('count', fn ($q) => $q->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->whereNotNull('counted_qty')
            ->whereRaw('variance != 0')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->stream('pdf.reports.cycle-count-variances', [
            'brandName' => SettingsService::get('company.name', config('app.name')),
            'lines'     => $lines,
            'from'      => $from,
            'to'        => $to,
        ], 'cycle-count-variances-' . $from . '-' . $to);
    }

    public function dispatchAccuracy(Request $request): Response
    {
        [$from, $to] = $this->dates($request);

        $orders = DeliveryOrder::with(['lines.item', 'lines.uom', 'consigneePartner', 'requestedBy'])
            ->whereNotIn('status', ['draft', 'submitted', 'approved', 'cancelled'])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->stream('pdf.reports.dispatch-accuracy', [
            'brandName' => SettingsService::get('company.name', config('app.name')),
            'orders'    => $orders,
            'from'      => $from,
            'to'        => $to,
        ], 'dispatch-accuracy-' . $from . '-' . $to);
    }

    public function stockAging(Request $request): Response
    {
        $balances = StockBalance::with(['item.baseUom', 'location', 'batch'])
            ->where('qty_on_hand', '>', 0)
            ->whereNotNull('received_at')
            ->orderBy('received_at')
            ->get()
            ->map(function ($b) {
                $b->age_days = (int) \Carbon\Carbon::parse($b->received_at)->diffInDays(now());
                $b->age_bucket = match (true) {
                    $b->age_days <= 30  => '0–30 days',
                    $b->age_days <= 60  => '31–60 days',
                    $b->age_days <= 90  => '61–90 days',
                    $b->age_days <= 180 => '91–180 days',
                    default             => '180+ days',
                };
                return $b;
            });

        return $this->stream('pdf.reports.stock-aging', [
            'brandName' => SettingsService::get('company.name', config('app.name')),
            'balances'  => $balances,
            'asOf'      => now()->format('d M Y H:i'),
        ], 'stock-aging-' . now()->format('Ymd'));
    }

    public function stockMovementHistory(Request $request): Response
    {
        [$from, $to] = $this->dates($request);

        $transactions = StockTransaction::with(['movements.item', 'movements.location', 'postedBy'])
            ->whereNotIn('type', [MovementType::Reversal->value, MovementType::SerialCapture->value])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        return $this->stream('pdf.reports.stock-movement-history', [
            'brandName'    => config('app.name'),
            'transactions' => $transactions,
            'from'         => $from,
            'to'           => $to,
        ], 'stock-movements-' . $from . '-' . $to);
    }
}
