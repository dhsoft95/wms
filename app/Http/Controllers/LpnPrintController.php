<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Grn;
use App\Models\Inventory\Lpn;
use App\Services\Inbound\LpnService;
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
            // Generate a new LPN for this GRN
            $lpn    = app(LpnService::class)->generate($grn);
            $lpnIds = [$lpn->id];
        }

        $html = app(LpnService::class)->printSheet($lpnIds, $grn);

        return response($html)->header('Content-Type', 'text/html');
    }
}
