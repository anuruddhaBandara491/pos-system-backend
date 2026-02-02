<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApi
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * Rate limiting configuration:
     * - General API: 60 requests per minute per IP/user
     * - Login/Auth: 5 requests per minute per IP
     * - Sensitive endpoints: 10 requests per minute per user
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ip = $request->ip();

        // Determine rate limit key and limit based on endpoint
        if ($request->is('api/v1/auth/login')) {
            // Strict limit for login attempts
            $key = "login:{$ip}";
            $limit = 5;
            $decayMinutes = 1;
        } elseif ($request->is('api/v1/auth/*')) {
            // Auth endpoints
            $key = $user ? "auth:{$user->id}" : "auth:{$ip}";
            $limit = 30;
            $decayMinutes = 1;
        } elseif ($request->is('api/v1/reports/*') || $request->is('api/v1/stock-movements/*')) {
            // Report and heavy endpoints
            $key = $user ? "heavy:{$user->id}" : "heavy:{$ip}";
            $limit = 30;
            $decayMinutes = 1;
        } else {
            // General API endpoints
            $key = $user ? "api:{$user->id}" : "api:{$ip}";
            $limit = 60;
            $decayMinutes = 1;
        }

        // Check rate limit
        if ($this->limiter->tooManyAttempts($key, $limit, $decayMinutes)) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'status' => 429,
            ], 429)
                ->header('Retry-After', $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        return $next($request)
            ->header('X-RateLimit-Limit', $limit)
            ->header('X-RateLimit-Remaining', $this->limiter->attempts($key) <= $limit
                ? $limit - $this->limiter->attempts($key)
                : 0);
    }
}
