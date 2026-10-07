<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use App\Support\ApiResponse;
use RuntimeException;
use Throwable;

/**
 * Base class for every deliberate, expected application failure.
 *
 * Two distinct messages exist on purpose:
 *
 *  - `getMessage()` is the *internal* message. It goes to logs and never to a
 *    client, so it may contain diagnostic detail (ids, reasons).
 *  - `userMessage()` is the *safe, localized* message the client receives.
 *
 * Anything that is not an AppException is treated as an unexpected bug and is
 * rendered as INTERNAL_ERROR, which stops internal details leaking by accident.
 */
abstract class AppException extends RuntimeException implements \Illuminate\Contracts\Debug\ShouldntReport
{
    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? $this->errorCode()->value, 0, $previous);
    }

    /**
     * The stable, machine-readable code returned to clients.
     */
    abstract public function errorCode(): ErrorCode;

    /**
     * HTTP status. Defaults to the canonical status for the error code so a
     * code cannot be served with two different statuses in two places.
     */
    public function status(): int
    {
        return ApiResponse::defaultStatus($this->errorCode());
    }

    /**
     * The localized, client-safe message.
     */
    public function userMessage(): string
    {
        return ApiResponse::messageFor($this->errorCode());
    }

    /**
     * Optional field-level details, shaped like validation errors.
     *
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return [];
    }
}
