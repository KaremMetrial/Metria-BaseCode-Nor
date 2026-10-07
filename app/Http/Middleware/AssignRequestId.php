<?php

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a correlation id to every request and echoes it back.
 *
 * The id is what lets a user's "it failed at 14:03" be matched to the exact
 * log line, audit row and (later) payment webhook.
 */
final class AssignRequestId
{
    public function __construct(private readonly RequestId $requestId) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->requestId->set($this->resolveId($request));

        $response = $next($request);

        $response->headers->set(RequestId::HEADER, $this->requestId->value());

        return $response;
    }

    private function resolveId(Request $request): string
    {
        $incoming = $request->header(RequestId::HEADER);

        // A client-supplied id is honoured so traces stitch together across
        // services, but only when it is strictly well-formed. It is echoed into
        // response headers and written to logs, so accepting arbitrary content
        // would allow header injection and log forging.
        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1) {
            return $incoming;
        }

        return RequestId::generate();
    }
}
