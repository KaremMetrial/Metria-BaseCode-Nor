<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces JSON negotiation for API traffic.
 *
 * Without this, a client that omits `Accept: application/json` gets an HTML
 * error page for validation failures (Laravel only redirects/renders JSON when
 * the request "expects" it), which breaks every mobile client.
 */
final class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
