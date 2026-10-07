<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetLocale;
use App\Support\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Correlation id: global, not group-scoped.
         *
         * Route-group middleware only runs once a route has matched, so putting
         * this in the `api` group would leave exactly the requests you most need
         * to trace -- 404s, 500s before routing -- without an id and without the
         * response header.
         */
        $middleware->prepend([AssignRequestId::class, SetLocale::class]);

        /*
         * JSON negotiation for API traffic only, so a client that forgets
         * `Accept: application/json` still gets JSON instead of an HTML error
         * page. Deliberately not global: web routes must keep rendering HTML.
         */
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        // Locale resolution is in the group (not per-route) so public endpoints
        // are localized too.

        $middleware->alias([
            'actor' => EnsureUserType::class,
            'active' => EnsureAccountIsActive::class,
            'locale' => SetLocale::class,

            /*
             * Spatie does not register these.
             *
             * This release exposes a `Route::permission()` macro instead of the
             * `permission`/`role` aliases its documentation shows, so without
             * the two entries below, `middleware('permission:...')` fails at
             * route-resolution with "Target class [permission] does not exist"
             * -- on every admin route, at once.
             */
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e) {
            if (app()->environment('production')) {
                Log::error('Unhandled application error', ['exception_class' => $e::class, 'file' => basename($e->getFile()), 'line' => $e->getLine(), 'request_id' => app(RequestId::class)->value()]);

                return false;
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // The single place exceptions become API responses. Returning null means
        // "not an API concern", which lets Laravel's default handling continue so
        // web routes keep their HTML error pages.
        $exceptions->render(
            fn (Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request),
        );
    })->create();
