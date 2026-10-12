<?php

namespace App\Http\Middleware;

use App\Support\ArabicText;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeInputDigits
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*/webhooks/*') && ! ($request->is('api/*/admin/integrations/*') && ! $request->isMethod('GET'))) {
            $request->query->replace($this->normalize($request->query->all()));
            $input = $request->isJson() ? $request->json() : $request->request;
            $input->replace($this->normalize($input->all()));
        }

        return $next($request);
    }

    /**
     * Preserve keys, types, leading zeroes, credentials and externally issued opaque values.
     *
     * @param  array<string|int, mixed>  $input
     * @return array<string|int, mixed>
     */
    private function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($key) && (preg_match('/password|token|secret|signature|credential|api_key|private_key|public_key|(?:^|_)url$/i', $key) || in_array($key, ['cursor', 'idempotency_key', 'request_id', 'client_message_id', 'device_id', 'challenge_id'], true))) {
                continue;
            }
            if (is_array($value)) {
                $input[$key] = $this->normalize($value);
            } elseif (is_string($value) && ! preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value)) {
                $input[$key] = ArabicText::digits($value);
            }
        }

        return $input;
    }
}
