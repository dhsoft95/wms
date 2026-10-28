<?php
declare(strict_types=1);
namespace App\Services\Scanning;

use App\Enums\ScanResultType;
use App\Models\Container;
use App\Models\Grn;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\SerialNumber;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\Location;
use App\Services\Foundation\ISO6346Validator;
use Illuminate\Database\Eloquent\Model;

final class BarcodeResolver
{
    public function resolve(string $raw, ?ScanContext $context = null): ScanResult
    {
        $raw = trim($raw);
        if ($raw === '') {
            return new ScanResult(ScanResultType::Unknown, error: 'Empty barcode');
        }

        // Amendment 1: GS1 first
        if ($this->isGs1($raw)) {
            return $this->resolveGs1($raw, $context);
        }

        // Prefix checks (single match, fast)
        if (str_starts_with($raw, 'LPN-')) {
            $lpn = Lpn::where('code', $raw)->first();
            return $lpn
                ? new ScanResult(ScanResultType::Lpn, record: $lpn)
                : new ScanResult(ScanResultType::Unknown, error: "LPN not found: {$raw}");
        }

        if (str_starts_with($raw, 'CTR-')) {
            $container = Container::where('internal_number', $raw)->first();
            return $container
                ? new ScanResult(ScanResultType::Container, record: $container)
                : new ScanResult(ScanResultType::Unknown, error: "Container not found: {$raw}");
        }

        // Amendment 4: ISO container — must match regex AND pass check digit
        if (preg_match('/^[A-Z]{4}\d{7}$/', $raw) && ISO6346Validator::isValid($raw)) {
            $container = Container::where('iso_number', $raw)->first();
            return $container
                ? new ScanResult(ScanResultType::Container, record: $container)
                : new ScanResult(ScanResultType::Unknown, error: "Container not found: {$raw}");
        }

        if (str_starts_with($raw, 'GRN-')) {
            $grn = Grn::where('number', $raw)->first();
            return $grn
                ? new ScanResult(ScanResultType::Grn, record: $grn)
                : new ScanResult(ScanResultType::Unknown, error: "GRN not found: {$raw}");
        }

        if (str_starts_with($raw, 'PO-')) {
            $po = \App\Models\PurchaseOrder::where('number', $raw)->first();
            return $po
                ? new ScanResult(ScanResultType::PurchaseOrder, record: $po)
                : new ScanResult(ScanResultType::Unknown, error: "Purchase Order not found: {$raw}");
        }

        if (str_starts_with($raw, 'DO-')) {
            return new ScanResult(ScanResultType::Unknown, error: 'Dispatch Orders not available in this module');
        }

        // Amendment 3: Collect ALL matches for remaining types
        return $this->resolveMulti($raw, $context);
    }

    private function isGs1(string $raw): bool
    {
        return str_starts_with($raw, ']C1')
            || str_starts_with($raw, ']d2')
            || str_starts_with($raw, ']Q3')
            || str_contains($raw, chr(29))
            || (bool) preg_match('/^\(01\)\d{14}/', $raw)
            || (bool) preg_match('/^\x1d?01\d{14}/', $raw);
    }

    private function resolveGs1(string $raw, ?ScanContext $context): ScanResult
    {
        $gs1 = $this->parseGs1($raw);
        if ($gs1 === null) {
            return new ScanResult(ScanResultType::Unknown, error: 'Could not parse GS1 barcode');
        }

        if (empty($gs1['gtin'])) {
            return new ScanResult(ScanResultType::Unknown, error: 'GS1 barcode has no GTIN');
        }

        $forms   = $this->normalizeGtin($gs1['gtin']);
        $barcode = ItemBarcode::whereIn('barcode', $forms)->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->first();

        if (! $barcode || ! $barcode->item) {
            return new ScanResult(ScanResultType::Unknown, error: "GTIN not found: {$gs1['gtin']}");
        }

        return new ScanResult(
            type:    ScanResultType::Item,
            record:  $barcode->item,
            gs1Data: $gs1,
        );
    }

    private function parseGs1(string $raw): ?array
    {
        // Strip symbology identifier prefix
        $raw = preg_replace('/^\](C1|d2|Q3)/', '', $raw);

        // Normalise: if human-readable (01)... format, strip parens
        if (str_contains($raw, '(')) {
            $raw = preg_replace('/\((\d+)\)/', chr(29) . '$1', $raw);
        }

        // Strip leading GS if present
        $raw = ltrim($raw, chr(29));

        $gs = chr(29);
        $result = ['gtin' => null, 'batch' => null, 'expiry' => null, 'serial' => null, 'qty' => null];
        $i = 0;
        $len = strlen($raw);

        while ($i < $len) {
            // Skip any GS separator between fields
            while ($i < $len && $raw[$i] === $gs) {
                $i++;
            }
            if ($i >= $len) break;

            // Try 2-char AI first, then 3-char, then 4-char
            $ai = null;
            foreach ([2, 3, 4] as $aiLen) {
                if ($i + $aiLen > $len) continue;
                $candidate = substr($raw, $i, $aiLen);
                if (in_array($candidate, ['01', '10', '17', '21', '30', '37'], true)) {
                    $ai = $candidate;
                    $i += $aiLen;
                    break;
                }
            }
            if ($ai === null) break;

            switch ($ai) {
                case '01': // GTIN-14, fixed 14 chars
                    $result['gtin'] = substr($raw, $i, 14);
                    $i += 14;
                    break;
                case '10': // batch, variable, GS terminated
                    $end = strpos($raw, $gs, $i);
                    $result['batch'] = $end === false ? substr($raw, $i) : substr($raw, $i, $end - $i);
                    $i = $end === false ? $len : $end + 1;
                    break;
                case '17': // expiry YYMMDD, fixed 6 chars
                    $yymmdd = substr($raw, $i, 6);
                    $i += 6;
                    $yy = (int) substr($yymmdd, 0, 2);
                    $mm = substr($yymmdd, 2, 2);
                    $dd = substr($yymmdd, 4, 2);
                    $year = $yy <= 30 ? 2000 + $yy : 1900 + $yy;
                    $result['expiry'] = "{$year}-{$mm}-{$dd}";
                    break;
                case '21': // serial, variable, GS terminated
                    $end = strpos($raw, $gs, $i);
                    $result['serial'] = $end === false ? substr($raw, $i) : substr($raw, $i, $end - $i);
                    $i = $end === false ? $len : $end + 1;
                    break;
                case '30': // qty, variable up to 8, GS terminated
                case '37':
                    $end = strpos($raw, $gs, $i);
                    $qtyStr = $end === false ? substr($raw, $i) : substr($raw, $i, $end - $i);
                    $result['qty'] = (int) $qtyStr;
                    $i = $end === false ? $len : $end + 1;
                    break;
                default:
                    $i = $len; // unknown AI, stop
            }
        }

        return $result['gtin'] !== null ? $result : null;
    }

    private function normalizeGtin(string $gtin14): array
    {
        $forms = [$gtin14];
        if (strlen($gtin14) === 14 && $gtin14[0] === '0') {
            $gtin13 = substr($gtin14, 1);
            $forms[] = $gtin13;
            if ($gtin14[1] === '0') {
                $forms[] = substr($gtin14, 2); // UPC-12
            }
        }
        return array_unique($forms);
    }

    private function resolveMulti(string $raw, ?ScanContext $context): ScanResult
    {
        $candidates = [];

        // Location by barcode or code
        $loc = Location::where('barcode', $raw)->orWhere('code', $raw)->first();
        if ($loc) {
            $candidates[] = new ScanResult(ScanResultType::Location, record: $loc);
        }

        // Item by item_barcodes — bypass VW scope (scanners see all items)
        $itemBarcode = ItemBarcode::where('barcode', $raw)->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->first();
        if ($itemBarcode?->item) {
            $candidates[] = new ScanResult(ScanResultType::Item, record: $itemBarcode->item);
        }

        // Item by SKU — bypass VW scope (scanners see all items)
        $item = Item::withoutGlobalScopes()->where('sku', $raw)->first();
        if ($item) {
            $alreadyFound = collect($candidates)->contains(
                fn($r) => $r->type === ScanResultType::Item && $r->record?->id === $item->id
            );
            if (! $alreadyFound) {
                $candidates[] = new ScanResult(ScanResultType::Item, record: $item);
            }
        }

        // Serial
        $serial = SerialNumber::where('serial_no', $raw)->first();
        if ($serial) {
            $candidates[] = new ScanResult(ScanResultType::Serial, record: $serial);
        }

        if (count($candidates) === 0) {
            return new ScanResult(ScanResultType::Unknown, error: "Barcode not recognised: {$raw}");
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        // Narrow by context
        if ($context?->expects !== null) {
            $narrowed = array_filter($candidates, fn($r) => $r->type === $context->expects);
            if (count($narrowed) === 1) {
                return array_values($narrowed)[0];
            }
        }

        return new ScanResult(
            type:       ScanResultType::Ambiguous,
            candidates: $candidates,
        );
    }
}
