<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Per-request correlation id.
 *
 * Registered as a singleton so the id resolved by the middleware is the same
 * one written to logs, audit rows and the response header. It lazily generates
 * its own value so console/queue contexts (where no request exists) still work.
 */
final class RequestId
{
    /**
     * Header clients may send to propagate their own correlation id.
     */
    public const HEADER = 'X-Request-Id';

    private ?string $value = null;

    public function value(): string
    {
        return $this->value ??= self::generate();
    }

    public function set(string $value): void
    {
        $this->value = $value;
    }

    public function has(): bool
    {
        return $this->value !== null;
    }

    public static function generate(): string
    {
        return (string) Str::uuid();
    }
}
