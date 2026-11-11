<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiJson
{
    public function handle(Request $request, Closure $next): Response
    {
        // Avoid authentication redirects even when a mobile client omits Accept.
        if ($request->is('api/v1', 'api/v1/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
