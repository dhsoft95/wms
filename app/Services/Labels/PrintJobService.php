<?php
declare(strict_types=1);
namespace App\Services\Labels;

use App\Enums\PrintOutput;
use App\Enums\PrinterKind;
use App\Models\LabelPrintJob;
use App\Models\LabelTemplate;
use App\Models\Inventory\Lpn;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class PrintJobService
{
    public function __construct(private readonly LabelRenderer $renderer) {}

    public function print(
        LabelTemplate $tpl,
        Model         $printable,
        Printer       $printer,
        int           $copies,
        User          $user,
        ?string       $reprintReasonCodeId = null,
        ?string       $reprintNote         = null,
    ): LabelPrintJob {
        $isReprint = false;

        if ($printable instanceof Lpn && $printable->print_count > 0) {
            $isReprint = true;
            Gate::forUser($user)->authorize('labels.reprint');
            if (! $reprintReasonCodeId) {
                throw new \InvalidArgumentException('A reprint reason code is required.');
            }
        }

        $row    = $this->buildDataRow($printable);
        $output = $this->outputForPrinter($printer);

        $rendered = $output === PrintOutput::Pdf
            ? $this->renderer->toPdf($tpl, [$row], $copies)
            : $this->renderer->toZpl($tpl, [$row], $copies);

        $job = LabelPrintJob::create([
            'label_template_id'      => $tpl->id,
            'type'                   => $tpl->type->value,
            'printable_type'         => get_class($printable),
            'printable_id'           => $printable->getKey(),
            'copies'                 => $copies,
            'printer_id'             => $printer->id,
            'output'                 => $output->value,
            'is_reprint'             => $isReprint,
            'reprint_reason_code_id' => $reprintReasonCodeId,
            'reprint_note'           => $reprintNote,
            'printed_by'             => $user->id,
        ]);

        if ($printable instanceof Lpn) {
            $printable->increment('print_count');
            $printable->update(['printed_at' => now()]);
        }

        return $job;
    }

    public function dispatchToPrinter(LabelPrintJob $job, Printer $printer, string $rendered): array
    {
        return match($printer->kind) {
            PrinterKind::PdfBrowser => [
                'success'      => true,
                'output'       => $rendered,
                'content_type' => 'application/pdf',
            ],
            PrinterKind::ZplDownload => [
                'success'      => true,
                'output'       => $rendered,
                'content_type' => 'application/octet-stream',
            ],
            PrinterKind::ZplNetwork => $this->sendZplOverNetwork($printer, $rendered),
        };
    }

    private function sendZplOverNetwork(Printer $printer, string $zpl): array
    {
        $socket = @fsockopen($printer->ip, $printer->port, $errno, $errstr, 5);

        if (! $socket) {
            return [
                'success'      => false,
                'error'        => "Cannot connect to printer {$printer->ip}:{$printer->port} — {$errstr}",
                'fallback_zpl' => $zpl,
            ];
        }

        fwrite($socket, $zpl);
        fclose($socket);

        return ['success' => true];
    }

    private function outputForPrinter(Printer $printer): PrintOutput
    {
        return match($printer->kind) {
            PrinterKind::ZplNetwork, PrinterKind::ZplDownload => PrintOutput::Zpl,
            default                                            => PrintOutput::Pdf,
        };
    }

    private function buildDataRow(Model $printable): array
    {
        return match(true) {
            $printable instanceof Lpn => [
                'lpn_code'       => $printable->code,
                'grn_number'     => $printable->grn?->number ?? '',
                'warehouse_name' => $printable->warehouse?->name ?? '',
                'item_sku'       => '',
                'item_name'      => '',
                'batch_no'       => '',
                'expiry_date'    => '',
                'qty'            => '',
                'uom_code'       => '',
                'vw_name'        => '',
                'receive_date'   => $printable->created_at?->format('Y-m-d') ?? '',
                'location_code'  => $printable->currentLocation?->code ?? '',
            ],
            default => [],
        };
    }
}
