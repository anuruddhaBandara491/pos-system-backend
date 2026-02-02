<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force JSON responses for API routes.
 * - Sets the Accept header to application/json on the request so Laravel
 *   returns JSON for validation/errors automatically.
 * - Ensures Content-Type is application/json for simple responses.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Ensure client expects JSON
        $request->headers->set('Accept', 'application/json');

        /** @var Response $response */
        $response = $next($request);

        // If response is a plain string/array wrapped in a Response, ensure JSON header
        $contentType = $response->headers->get('Content-Type');

        if (! $contentType || stripos($contentType, 'application/json') === false) {
            // Only set header if response is not a streaming response
            // (streaming responses should not have their content-type altered)
            if ($response->isClientError() || $response->isServerError() || $response->isSuccessful()) {
                $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            }
        }

        return $response;
    }
}
