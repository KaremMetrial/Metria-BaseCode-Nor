<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Exceptions\Authorization\ForbiddenException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an actor surface (`/admin`, `/client`, `/vendor`).
 *
 * This exists because a route *prefix* is not authorization. A client's token
 * is a perfectly valid Sanctum token, so `auth:sanctum` alone would happily let
 * it call admin endpoints. Every actor group must carry `actor:<type>`.
 *
 * Registered as the `actor` alias. Usage: ->middleware('actor:admin,vendor')
 */
final class EnsureUserType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        // Defense in depth: this middleware is safe to use even if it is ever
        // mounted without `auth:sanctum`.
        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (! in_array($user->type, $this->parseTypes($types), true)) {
            // The specific mismatch is logged but never returned, so a client
            // cannot probe which actor surfaces exist.
            throw ForbiddenException::make(sprintf(
                'User %s has type "%s" but this route requires one of: %s',
                $user->getKey(),
                $user->type->value,
                implode(', ', $types),
            ));
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $types
     * @return list<UserType>
     */
    private function parseTypes(array $types): array
    {
        return array_map(function (string $type): UserType {
            // An unknown alias is a developer error in a route definition, not a
            // client error, so it must fail loudly rather than silently denying
            // access (which would look like a permissions bug).
            return UserType::tryFrom($type)
                ?? throw new InvalidArgumentException(
                    "Unknown user type '{$type}' in route middleware. Known types: ".implode(', ', UserType::values())
                );
        }, $types);
    }
}
