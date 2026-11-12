<?php

declare(strict_types=1);

namespace App\Services\Labels;

use App\Enums\LabelTemplateType;
use App\Enums\PrintOutput;
use App\Enums\PrinterKind;
use App\Models\Container;
use App\Models\Dispatch;
use App\Models\Inventory\Lpn;
use App\Models\Item;
use App\Models\LabelPrintJob;
use App\Models\LabelTemplate;
use App\Models\Location;
use App\Models\Printer;
use App\Models\User;
use App\Services\Labels\Data\ContainerLabelData;
use App\Services\Labels\Data\DispatchLabelData;
use App\Services\Labels\Data\ItemLabelData;
use App\Services\Labels\Data\LocationLabelData;
use App\Services\Labels\Data\LpnLabelData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PrintJobService
{
    public function __construct(private readonly LabelRenderer $renderer) {}

    /**
     * Print one or many printables of the same type.
     * Creates one LabelPrintJob per printable. Returns a single PrintResult.
     */
    public function print(
        LabelTemplateType $type,
        iterable          $printables,
        Printer           $printer,
        int               $copies,
        User              $user,
        ?LabelTemplate    $tpl          = null,
        ?string           $reasonCodeId = null,
        ?string           $note         = null,
    ): PrintResult {
        Log::debug('PrintJobService: checking labels.print permission', ['user_id' => $user->id]);
        Gate::forUser($user)->authorize('labels.print');

        $tpl ??= LabelTemplate::where('type', $type->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();

        $output = $this->outputForPrinter($printer);
        Log::debug('PrintJobService: output mode determined', ['output' => $output->value, 'printer_kind' => $printer->kind->value]);

        $rows = [];

        foreach ($printables as $printable) {
            $isReprint = $this->isReprint($printable);
            Log::debug('PrintJobService: building data row', ['printable' => get_class($printable), 'id' => $printable->getKey(), 'is_reprint' => $isReprint]);

            if ($isReprint) {
                Gate::forUser($user)->authorize('labels.reprint');
                if (! $reasonCodeId) {
                    throw new \InvalidArgumentException('A reprint reason code is required.');
                }
            }

            $rows[] = $this->buildDataRow($type, $printable);

            LabelPrintJob::create([
                'label_template_id'      => $tpl->id,
                'type'                   => $type->value,
                'printable_type'         => get_class($printable),
                'printable_id'           => $printable->getKey(),
                'copies'                 => $copies,
                'printer_id'             => $printer->id,
                'output'                 => $output->value,
                'is_reprint'             => $isReprint,
                'reprint_reason_code_id' => $isReprint ? $reasonCodeId : null,
                'reprint_note'           => $isReprint ? ($note ?: null) : null,
                'printed_by'             => $user->id,
                'created_at'             => now(),
            ]);

            if ($printable instanceof Lpn) {
                $printable->increment('print_count');
                $printable->update(['printed_at' => now()]);
            }
        }

        Log::debug('PrintJobService: rendering', ['output' => $output->value, 'rows' => count($rows), 'copies' => $copies]);

        $rendered = $output === PrintOutput::Pdf
            ? $this->renderer->toPdf($tpl, $rows, $copies)
            : $this->renderer->toZpl($tpl, $rows, $copies);

        Log::debug('PrintJobService: render complete', ['rendered_size' => strlen($rendered)]);

        return $this->buildResult($printer, $output, $rendered);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildResult(Printer $printer, PrintOutput $output, string $rendered): PrintResult
    {
        if ($output === PrintOutput::Pdf) {
            $token = Str::uuid()->toString();
            Cache::put("label_pdf:{$token}", base64_encode($rendered), now()->addMinutes(15));
            Log::debug('PrintJobService: PDF cached', ['token' => $token, 'cache_driver' => config('cache.default')]);
            return new PrintResult(outputType: 'pdf', content: $rendered, pdfToken: $token);
        }

        if ($printer->kind === PrinterKind::ZplNetwork) {
            Log::debug('PrintJobService: sending ZPL over network', ['ip' => $printer->ip, 'port' => $printer->port]);
            $result = $this->sendZplOverNetwork($printer, $rendered);
            if ($result['success']) {
                Log::info('PrintJobService: ZPL sent to network printer', ['printer' => $printer->name]);
                return new PrintResult(outputType: 'sent', printerName: $printer->name);
            }
            Log::warning('PrintJobService: ZPL network send failed, falling back to download', ['error' => $result['error'] ?? 'unknown']);
            return new PrintResult(outputType: 'zpl', content: $rendered);
        }

        Log::debug('PrintJobService: returning ZPL for download');
        return new PrintResult(outputType: 'zpl', content: $rendered);
    }

    private function isReprint(Model $printable): bool
    {
        return $printable instanceof Lpn && $printable->print_count > 0;
    }

    private function outputForPrinter(Printer $printer): PrintOutput
    {
        return match($printer->kind) {
            PrinterKind::ZplNetwork, PrinterKind::ZplDownload => PrintOutput::Zpl,
            default                                            => PrintOutput::Pdf,
        };
    }

    private function buildDataRow(LabelTemplateType $type, Model $printable): array
    {
        return match($type) {
            LabelTemplateType::Location  => LocationLabelData::build($printable),
            LabelTemplateType::PalletLpn => LpnLabelData::build($printable),
            LabelTemplateType::Item      => ItemLabelData::build($printable),
            LabelTemplateType::Container => ContainerLabelData::build($printable),
            LabelTemplateType::Dispatch  => DispatchLabelData::build($printable),
        };
    }

    private function sendZplOverNetwork(Printer $printer, string $zpl): array
    {
        $socket = @fsockopen($printer->ip, $printer->port, $errno, $errstr, 5);

        if (! $socket) {
            return [
                'success' => false,
                'error'   => "Cannot connect to {$printer->ip}:{$printer->port} — {$errstr}",
            ];
        }

        fwrite($socket, $zpl);
        fclose($socket);

        return ['success' => true];
    }
}
