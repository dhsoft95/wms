<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Inventory\MovementType;
use App\Exports\Reports\ReportExcelExport;
use App\Reports\Export\ExcelStyler;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\ReportRequest;
use App\Notifications\ReportReadyNotification;
use App\Services\Foundation\SettingsService;
use App\Services\Reports\ReportRegistry;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(public ReportRequest $request) {}

    public function handle(): void
    {
        $this->request->update(['status' => 'processing']);

        try {
            if ($this->request->isRegistryReport()) {
                $this->handleRegistryReport();
                return;
            }

            [$view, $data, $filename] = $this->buildReport();

            $disk = $this->disk();
            $disk->makeDirectory('reports');

            $pdf  = Pdf::loadView($view, $data)->setPaper([0, 0, 595.28, 841.89], 'portrait');
            $path = 'reports/' . $this->request->id . '_' . $filename . '.pdf';

            $disk->put($path, $pdf->output());

            $this->request->update(['status' => 'ready', 'file_path' => $path]);

        } catch (Throwable $e) {
            Log::error('GenerateReportJob failed', [
                'report_request_id' => $this->request->id,
                'report_key'        => $this->request->type,
                'format'            => $this->request->format,
                'filters'           => $this->request->filters,
                'user_id'           => $this->request->user_id,
                'error'             => $e->getMessage(),
                'file'              => $e->getFile(),
                'line'              => $e->getLine(),
            ]);

            $this->request->update([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('reports.disk', 'local'));
    }

    private function handleRegistryReport(): void
    {
        $registry   = app(ReportRegistry::class);
        $definition = $registry->find($this->request->type);

        if ($definition === null) {
            throw new \InvalidArgumentException("Report definition '{$this->request->type}' not found.");
        }

        $user    = $this->request->user;
        $filters = $this->request->filters ?? [];
        $format  = $this->request->format ?? 'excel';
        $disk    = $this->disk();

        $disk->makeDirectory('reports');

        if ($format === 'excel') {
            $brand   = PdfBrand::make();
            $rows    = $definition->query($filters, $user)->get();

            $tmpPath = tempnam(sys_get_temp_dir(), 'wms_report_');
            $tmpXlsx = $tmpPath . '.xlsx';

            try {
                (new ExcelStyler())->write($tmpXlsx, $definition, $rows, $brand, $filters);
                $storagePath = 'reports/' . $this->request->id . '_' . $definition->key() . '_' . now()->format('Ymd-Hi') . '.xlsx';
                $disk->put($storagePath, file_get_contents($tmpXlsx));
            } finally {
                @unlink($tmpPath);
                @unlink($tmpXlsx);
            }

        } elseif ($format === 'pdf' && $definition->pdfEnabled()) {
            if (method_exists($definition, 'pdfView') && $definition->pdfView()) {
                $viewName = $definition->pdfView();
                $data     = method_exists($definition, 'pdfData')
                    ? $definition->pdfData($filters, $user)
                    : [];

                $filename = method_exists($definition, 'pdfFilename')
                    ? $definition->pdfFilename($filters)
                    : $definition->key() . '_' . now()->format('Ymd-Hi');

                $pdf = Pdf::loadView($viewName, $data)
                    ->setOptions(['isPhpEnabled' => true])
                    ->setPaper([0, 0, 841.89, 595.28], 'landscape');

                $storagePath = 'reports/' . $this->request->id . '_' . $filename . '.pdf';
                $disk->put($storagePath, $pdf->output());
            } else {
                $rows        = $definition->query($filters, $user)->get();
                $colCount    = count($definition->columns());
                $isLandscape = $colCount > 7;
                $paper       = $isLandscape ? [0, 0, 841.89, 595.28] : [0, 0, 595.28, 841.89];
                $orient      = $isLandscape ? 'landscape' : 'portrait';
                $pdf = Pdf::loadView('reports.layouts.pdf', [
                    'definition'  => $definition,
                    'rows'        => $rows,
                    'filters'     => $filters,
                    'brand'       => PdfBrand::make(),
                    'summary'     => $definition->summary($rows),
                    'totals'      => $definition->totals($rows),
                    'generatedAt' => now()->format('d M Y H:i'),
                ])->setOptions(['isPhpEnabled' => true])->setPaper($paper, $orient);

                $storagePath = 'reports/' . $this->request->id . '_' . $definition->key() . '_' . now()->format('Ymd-Hi') . '.pdf';
                $disk->put($storagePath, $pdf->output());
            }

        } else {
            throw new \InvalidArgumentException("Unsupported format '{$format}' for report '{$this->request->type}'.");
        }

        $this->request->update([
            'status'      => 'ready',
            'file_path'   => $storagePath,
            'notified_at' => now(),
        ]);

        $user->notify(new ReportReadyNotification($this->request));
    }

    private function buildReport(): array
    {
        $from      = $this->request->date_from ? Carbon::parse($this->request->date_from)->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d');
        $to        = $this->request->date_to   ? Carbon::parse($this->request->date_to)->format('Y-m-d')   : now()->format('Y-m-d');
        $brand     = SettingsService::get('company.name', config('app.name'));

        return match ($this->request->type) {
            'stock-valuation'        => $this->stockValuation($brand, $from, $to),
            'receiving-summary'      => $this->receivingSummary($brand, $from, $to),
            'cycle-count-variances'  => $this->cycleCountVariances($brand, $from, $to),
            'dispatch-accuracy'      => $this->dispatchAccuracy($brand, $from, $to),
            'stock-aging'            => $this->stockAging($brand),
            'stock-movement-history' => $this->stockMovementHistory($brand, $from, $to),
            default                  => throw new \InvalidArgumentException("Unknown report type: {$this->request->type}"),
        };
    }

    private function stockValuation(string $brand, string $from, string $to): array
    {
        $balances = StockBalance::with(['item.baseUom', 'location', 'batch'])
            ->where('qty_on_hand', '>', 0)
            ->orderBy('item_id')
            ->orderBy('location_id')
            ->get();

        return [
            'pdf.reports.stock-valuation',
            ['brandName' => $brand, 'balances' => $balances, 'asOf' => now()->format('d M Y H:i')],
            'stock-valuation-' . now()->format('Ymd'),
        ];
    }

    private function receivingSummary(string $brand, string $from, string $to): array
    {
        $grns = Grn::with(['purchaseOrder.supplierPartner', 'warehouse', 'lines.item', 'lines.uom', 'confirmedBy'])
            ->whereNotNull('confirmed_at')
            ->whereBetween('confirmed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('confirmed_at')
            ->get();

        return [
            'pdf.reports.receiving-summary',
            ['brandName' => $brand, 'grns' => $grns, 'from' => $from, 'to' => $to],
            'receiving-summary-' . $from . '-' . $to,
        ];
    }

    private function cycleCountVariances(string $brand, string $from, string $to): array
    {
        $lines = CycleCountLine::with(['count', 'item', 'location'])
            ->whereHas('count', fn ($q) => $q->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->whereNotNull('counted_qty')
            ->whereRaw('variance != 0')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'pdf.reports.cycle-count-variances',
            ['brandName' => $brand, 'lines' => $lines, 'from' => $from, 'to' => $to],
            'cycle-count-variances-' . $from . '-' . $to,
        ];
    }

    private function dispatchAccuracy(string $brand, string $from, string $to): array
    {
        $orders = DeliveryOrder::with(['lines.item', 'lines.uom', 'consigneePartner', 'requestedBy'])
            ->whereNotIn('status', ['draft', 'submitted', 'approved', 'cancelled'])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'pdf.reports.dispatch-accuracy',
            ['brandName' => $brand, 'orders' => $orders, 'from' => $from, 'to' => $to],
            'dispatch-accuracy-' . $from . '-' . $to,
        ];
    }

    private function stockAging(string $brand): array
    {
        $balances = StockBalance::with(['item.baseUom', 'location', 'batch'])
            ->where('qty_on_hand', '>', 0)
            ->whereNotNull('received_at')
            ->orderBy('received_at')
            ->get()
            ->map(function ($b) {
                $b->age_days   = (int) Carbon::parse($b->received_at)->diffInDays(now());
                $b->age_bucket = match (true) {
                    $b->age_days <= 30  => '0–30 days',
                    $b->age_days <= 60  => '31–60 days',
                    $b->age_days <= 90  => '61–90 days',
                    $b->age_days <= 180 => '91–180 days',
                    default             => '180+ days',
                };
                return $b;
            });

        return [
            'pdf.reports.stock-aging',
            ['brandName' => $brand, 'balances' => $balances, 'asOf' => now()->format('d M Y H:i')],
            'stock-aging-' . now()->format('Ymd'),
        ];
    }

    private function stockMovementHistory(string $brand, string $from, string $to): array
    {
        $transactions = StockTransaction::with(['movements.item', 'movements.location', 'postedBy'])
            ->whereNotIn('type', [MovementType::Reversal->value, MovementType::SerialCapture->value])
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        return [
            'pdf.reports.stock-movement-history',
            ['brandName' => $brand, 'transactions' => $transactions, 'from' => $from, 'to' => $to],
            'stock-movements-' . $from . '-' . $to,
        ];
    }
}
