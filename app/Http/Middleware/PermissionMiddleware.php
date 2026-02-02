<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if authenticated user has a specific permission.
 * Usage: Route::middleware('permission:record_payment')->post('/payment', ...);
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($request->user() === null) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        foreach ($permissions as $permission) {
            if ($request->user()->can($permission)) {
                return $next($request);
            }
        }

        return response()->json(
            ['success' => false, 'message' => 'Unauthorized: insufficient permissions'],
            403
        );
    }
}
