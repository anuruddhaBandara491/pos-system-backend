<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if authenticated user has a specific role.
 * Usage: Route::middleware('role:cashier')->post('/order', ...);
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if ($request->user() === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        foreach ($roles as $role) {
            if ($request->user()->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json(
            ['success' => false, 'message' => 'Unauthorized: insufficient role'],
            403
        );
    }
}
