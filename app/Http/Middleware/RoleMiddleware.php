<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage in routes: ->middleware('role:admin') or 'role:admin|finance'
     */
    public function handle(Request $request, Closure $next, $roles = null)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (! $roles) {
            return $next($request);
        }

        // Support pipe or comma separated lists
        $roles = str_replace(',', '|', $roles);
        $allowed = array_map('trim', explode('|', $roles));

        if (! in_array($user->role, $allowed)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
