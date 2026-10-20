<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Inventory\MovementType;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\ReportRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
            [$view, $data, $filename] = $this->buildReport();

            $pdf  = Pdf::loadView($view, $data)->setPaper([0, 0, 595.28, 841.89], 'portrait');
            $path = 'reports/' . $this->request->id . '_' . $filename . '.pdf';

            Storage::put($path, $pdf->output());

            $this->request->update(['status' => 'ready', 'file_path' => $path]);
        } catch (Throwable $e) {
            $this->request->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function buildReport(): array
    {
        $from      = $this->request->date_from?->format('Y-m-d') ?? now()->startOfMonth()->format('Y-m-d');
        $to        = $this->request->date_to?->format('Y-m-d')   ?? now()->format('Y-m-d');
        $brand     = config('app.name');
        $fromLabel = Carbon::parse($from)->format('d M Y');
        $toLabel   = Carbon::parse($to)->format('d M Y');

        return match ($this->request->type) {
            'stock-valuation'       => $this->stockValuation($brand, $from, $to),
            'receiving-summary'     => $this->receivingSummary($brand, $from, $to),
            'cycle-count-variances' => $this->cycleCountVariances($brand, $from, $to),
            'dispatch-accuracy'     => $this->dispatchAccuracy($brand, $from, $to),
            'stock-aging'           => $this->stockAging($brand),
            'stock-movement-history'=> $this->stockMovementHistory($brand, $from, $to),
            default                 => throw new \InvalidArgumentException("Unknown report type: {$this->request->type}"),
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
