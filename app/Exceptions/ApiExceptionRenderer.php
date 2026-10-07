<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The one place exceptions become HTTP responses.
 *
 * Keeping this out of bootstrap/app.php means the mapping is unit-testable and
 * the routing bootstrap stays readable. Returning null means "not mine", which
 * lets Laravel's default handling continue (so web routes keep their HTML error
 * pages).
 *
 * Unhandled API failures always use a localized, safe 500 envelope.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::isApiRequest($request)) {
            return null;
        }

        if ($e instanceof UniqueConstraintViolationException) {
            return ApiResponse::error(ErrorCode::RESOURCE_CONFLICT);
        }
        if ($e instanceof QueryException && in_array((int) ($e->errorInfo[1] ?? 0), [1451, 1452], true)) {
            return ApiResponse::error(ErrorCode::RESOURCE_IN_USE);
        }

        // Deliberate, expected domain failures.
        if ($e instanceof AppException) {
            return ApiResponse::error(
                $e->errorCode(),
                $e->userMessage(),
                $e->errors(),
                $e->status(),
            );
        }

        if ($e instanceof ValidationException) {
            return ApiResponse::error(ErrorCode::VALIDATION_FAILED, null, $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(ErrorCode::UNAUTHENTICATED, null, [], 401);
        }

        if ($e instanceof AuthorizationException) {
            return ApiResponse::error(ErrorCode::FORBIDDEN, null, [], 403);
        }

        if ($e instanceof ThrottleRequestsException) {
            return ApiResponse::error(ErrorCode::RATE_LIMITED, null, [], 429)->withHeaders($e->getHeaders());
        }

        // These must precede the generic HttpExceptionInterface branch below,
        // because they are themselves HttpExceptions.
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::error(ErrorCode::NOT_FOUND, null, [], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(ErrorCode::METHOD_NOT_ALLOWED, null, [], 405);
        }

        if ($e instanceof HttpExceptionInterface) {
            return ApiResponse::error(
                self::codeForStatus($e->getStatusCode()),
                null,
                [],
                $e->getStatusCode(),
            );
        }

        return ApiResponse::error(ErrorCode::INTERNAL_ERROR, null, [], 500);
    }

    private static function codeForStatus(int $status): ErrorCode
    {
        return match ($status) {
            401 => ErrorCode::UNAUTHENTICATED,
            403 => ErrorCode::FORBIDDEN,
            404 => ErrorCode::NOT_FOUND,
            405 => ErrorCode::METHOD_NOT_ALLOWED,
            429 => ErrorCode::RATE_LIMITED,
            default => ErrorCode::INTERNAL_ERROR,
        };
    }

    /**
     * Only API traffic is reshaped. `api/*` is checked explicitly because a
     * client that forgets `Accept: application/json` must still get JSON, not
     * an HTML error page.
     */
    private static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}
