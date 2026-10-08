<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict scanner-operator users to /scan/* routes only.
 * Any other authenticated route returns 403.
 */
class ScannerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('scanner-operator') && ! $request->routeIs('scan.*', 'logout')) {
            abort(403, 'Scanner operators may only access the scanning interface.');
        }

        return $next($request);
    }
}
