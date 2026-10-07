<?php

namespace App\Http\Middleware;

use App\Exceptions\Localization\UnsupportedLocaleException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one place the active locale is decided.
 *
 * Resolution order:
 *   1. An explicit request locale (?locale= or the X-Locale header). Rejected
 *      loudly when unsupported, because the client asked for it deliberately.
 *   2. The authenticated user's saved preference.
 *   3. A negotiated Accept-Language match. Never rejected: browsers send this
 *      header automatically, so failing the request would be hostile.
 *   4. The application default.
 *
 * No controller or resource ever inspects a locale string itself.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('languages.default','en'));
        if (! $request->is('api/*')) { return $next($request); }
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = array_keys(config('languages.supported', []));
        $default = (string) config('languages.default', config('app.locale'));

        $explicit = $request->query('locale')
            ?? $request->header((string) config('languages.header', 'X-Locale'));

        if (is_string($explicit) && $explicit !== '') {
            $normalized = strtolower($explicit);

            if (! in_array($normalized, $supported, true)) {
                throw UnsupportedLocaleException::make($explicit);
            }

            return $normalized;
        }

        $userLocale = $this->resolveUserLocale($request, $supported);

        if ($userLocale !== null) {
            return $userLocale;
        }

        foreach ($request->getLanguages() as $candidate) {
            $normalized = strtolower((string) $candidate);

            if (in_array($normalized, $supported, true)) {
                return $normalized;
            }

            // Regional variants degrade to their base language: "ar-EG" -> "ar".
            $base = explode('-', str_replace('_','-',$normalized))[0];

            if (in_array($base, $supported, true)) {
                return $base;
            }
        }

        return $default;
    }

    /**
     * @param  list<string>  $supported
     */
    private function resolveUserLocale(Request $request, array $supported): ?string
    {
        // This middleware runs in the `api` group, i.e. *before* route
        // middleware, so `auth:sanctum` has not yet made `sanctum` the default
        // guard. Resolving through it explicitly means a token-authenticated
        // user still gets their saved language on the very first request.
        $user = $request->user() ?? Auth::guard('sanctum')->user();

        $locale = $user?->getAttribute('locale');

        return is_string($locale) && in_array($locale, $supported, true) ? $locale : null;
    }
}
