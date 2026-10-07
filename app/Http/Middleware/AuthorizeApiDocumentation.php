<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Access\RoleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeApiDocumentation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'development'])) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        abort_unless($user instanceof User && $user->isAdmin() && $user->isActive() && $user->hasRole(RoleRegistry::SUPER_ADMIN), 403);

        return $next($request);
    }
}
