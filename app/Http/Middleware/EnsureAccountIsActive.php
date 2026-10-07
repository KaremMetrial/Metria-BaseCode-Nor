<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Exceptions\Authentication\AccountDisabledException;
use App\Exceptions\Authentication\AccountPendingException;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a fully active account (registered as the `active` alias).
 *
 * Pairs with UserStatus: a `pending` vendor is allowed to authenticate (so it
 * can see "your account is under review") but is blocked here from any surface
 * that assumes an approved account. Blocked/suspended accounts never get this
 * far because they cannot authenticate at all.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $status = $user->status;

        if ($status === UserStatus::PENDING) {
            throw AccountPendingException::make();
        }

        if (! $status->canAuthenticate()) {
            throw AccountDisabledException::make($status);
        }

        return $next($request);
    }
}
