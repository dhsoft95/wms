<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LabelTemplateType;
use App\Models\Grn;
use App\Models\Inventory\Lpn;
use App\Models\LabelTemplate;
use App\Services\Inbound\LpnService;
use App\Services\Labels\Data\LpnLabelData;
use App\Services\Labels\LabelRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LpnPrintController extends Controller
{
    public function __invoke(Request $request, Grn $grn): Response
    {
        Gate::authorize('lpn.print');

        $lpnIds = $request->input('lpns');
        if (! is_array($lpnIds)) {
            $lpnIds = [$lpnIds];
        }

        $lpnIds = array_filter(array_map('intval', $lpnIds));

        if (empty($lpnIds)) {
            $lpn    = app(LpnService::class)->generate($grn);
            $lpnIds = [$lpn->id];
        }

        $tpl = LabelTemplate::where('type', LabelTemplateType::PalletLpn->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? LabelTemplate::where('type', LabelTemplateType::PalletLpn->value)
                ->where('is_active', true)
                ->first();

        if (! $tpl) {
            abort(404, 'No active LPN label template found.');
        }

        $lpns = Lpn::whereIn('id', $lpnIds)->get();

        // Increment print counts
        $lpns->each(function (Lpn $lpn): void {
            $lpn->increment('print_count');
            $lpn->updateQuietly(['printed_at' => now()]);
        });

        $rows = $lpns->map(fn (Lpn $lpn) => LpnLabelData::build($lpn))->all();

        $pdf = app(LabelRenderer::class)->toPdf($tpl, $rows);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="lpn-labels.pdf"',
        ]);
    }
}
