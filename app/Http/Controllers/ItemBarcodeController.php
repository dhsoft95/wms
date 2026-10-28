<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LabelTemplateType;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\LabelTemplate;
use App\Services\Labels\LabelRenderer;
use Com\Tecnick\Barcode\Barcode;
use Symfony\Component\HttpFoundation\Response;

final class ItemBarcodeController extends Controller
{
    public function png(Item $item, ItemBarcode $itemBarcode): Response
    {
        abort_if($itemBarcode->item_id !== $item->id, 404);

        $symbology = self::detectSymbology($itemBarcode->barcode);

        try {
            $bobj = (new Barcode())->getBarcodeObj($symbology, $itemBarcode->barcode, 400, 120);
            $png  = $bobj->getPngData(true);
        } catch (\Throwable) {
            abort(422, 'Barcode could not be rendered.');
        }

        return response($png, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'inline; filename="' . $itemBarcode->barcode . '.png"',
            'Cache-Control'       => 'public, max-age=86400',
        ]);
    }

    public function itemLabel(Item $item): Response
    {
        $template = LabelTemplate::defaultForType(LabelTemplateType::Item)->first()
            ?? LabelTemplate::where('type', LabelTemplateType::Item->value)
                ->where('is_active', true)
                ->first();

        abort_if($template === null, 404, 'No active Item label template configured.');

        $pdf = app(LabelRenderer::class)->toPdf($template, [[
            'item_sku'  => $item->sku,
            'item_name' => $item->name,
            'barcode'   => $item->sku,
            'uom_code'  => $item->baseUom?->code ?? '',
        ]]);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . $item->sku . '.pdf"',
        ]);
    }

    public function label(Item $item, ItemBarcode $itemBarcode): Response
    {
        abort_if($itemBarcode->item_id !== $item->id, 404);

        $template = LabelTemplate::defaultForType(LabelTemplateType::Item)->first()
            ?? LabelTemplate::where('type', LabelTemplateType::Item->value)
                ->where('is_active', true)
                ->first();

        abort_if($template === null, 404, 'No active Item label template configured.');

        $pdf = app(LabelRenderer::class)->toPdf($template, [[
            'item_sku'  => $item->sku,
            'item_name' => $item->name,
            'barcode'   => $itemBarcode->barcode,
            'uom_code'  => $itemBarcode->uom?->code ?? $item->baseUom?->code ?? '',
        ]]);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . $item->sku . '.pdf"',
        ]);
    }

    private static function detectSymbology(string $value): string
    {
        if (preg_match('/^\d{13}$/', $value)) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $value[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            if ((10 - ($sum % 10)) % 10 === (int) $value[12]) {
                return 'EAN13';
            }
        }

        return 'C128';
    }
}
