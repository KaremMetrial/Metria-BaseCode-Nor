<?php

namespace App\Support;

use App\Enums\ErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Builds the single response envelope used by every API endpoint.
 *
 * Success:
 *   { "success": true,  "message": "...", "data": {...} }
 *
 * Failure:
 *   { "success": false, "code": "STABLE_CODE", "message": "...", "errors": {} }
 *
 * `code` is a stable, untranslated contract value (see App\Enums\ErrorCode);
 * `message` is localized for the active request locale.
 */
final class ApiResponse
{
    public static function paginated(string $resource, LengthAwarePaginator $page): JsonResponse
    {
        return self::success(['items' => $resource::collection($page->items()), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors  Field-level details, e.g. validation errors.
     */
    public static function error(
        ErrorCode $code,
        ?string $message = null,
        array $errors = [],
        ?int $status = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'code' => $code->value,
            'message' => $message ?? self::messageFor($code),
            // Cast so an empty payload serialises as {} rather than [].
            'errors' => (object) $errors,
        ], $status ?? self::defaultStatus($code));
    }

    /**
     * Resolve the localized message for a code, falling back to a generic
     * message if a translation is missing (so a missing lang key can never leak
     * a raw key like "errors.PHONE_ALREADY_EXISTS" to a client).
     */
    public static function messageFor(ErrorCode $code, ?string $locale = null): string
    {
        $key = "errors.{$code->value}";

        $message = $locale === null ? __($key) : trans($key, [], $locale);

        return $message === $key ? __('errors.INTERNAL_ERROR') : $message;
    }

    /**
     * Status codes live here rather than at every call site, so the same code
     * can never be returned with two different HTTP statuses.
     */
    public static function defaultStatus(ErrorCode $code): int
    {
        return match ($code) {
            ErrorCode::UNAUTHENTICATED,
            ErrorCode::INVALID_CREDENTIALS => 401,

            ErrorCode::FORBIDDEN,
            ErrorCode::ACCOUNT_DISABLED,
            ErrorCode::ACCOUNT_PENDING => 403,

            ErrorCode::NOT_FOUND => 404,
            ErrorCode::METHOD_NOT_ALLOWED => 405,

            ErrorCode::PHONE_ALREADY_EXISTS,
            ErrorCode::RESOURCE_IN_USE, ErrorCode::RESOURCE_CONFLICT => 409,

            ErrorCode::VALIDATION_FAILED,
            ErrorCode::UNSUPPORTED_LOCALE,
            ErrorCode::INVALID_PHONE_NUMBER,
            ErrorCode::PHONE_REQUIRED,
            ErrorCode::PHONE_COUNTRY_MISMATCH,
            ErrorCode::UNSUPPORTED_PHONE_COUNTRY,
            ErrorCode::PHONE_TYPE_NOT_ALLOWED,
            ErrorCode::PHONE_NOT_VERIFIED,
            ErrorCode::INVALID_LOCATION_HIERARCHY => 422,

            ErrorCode::RATE_LIMITED => 429,

            ErrorCode::PROVIDER_UNAVAILABLE => 503,
            ErrorCode::OTP_COOLDOWN, ErrorCode::OTP_ATTEMPTS_EXCEEDED => 429,
            ErrorCode::INVALID_WEBHOOK => 400,
            ErrorCode::IDEMPOTENCY_CONFLICT, ErrorCode::RECONCILIATION_REQUIRED, ErrorCode::PAYMENT_ALREADY_REFUNDED => 409,
            ErrorCode::INTERNAL_ERROR => 500,
            default => 422,
        };
    }
}
