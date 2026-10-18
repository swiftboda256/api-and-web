<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a token issued to one app (e.g. rider-app) from being used
 * against the other app's routes (e.g. user-app), since both apps
 * authenticate the same User model over the same Sanctum guard.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole($role)) {
            return Controller::error('You are not authorized to access this resource.', 403);
        }

        return $next($request);
    }
}
