<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Http\JsonResponse;

/**
 * Base controller for API controllers.
 * Provides standardized JSON responses and ensures API middleware is applied.
 *
 * All responses follow this format:
 * Success: {"success": true, "message": "...", "data": {...}}
 * Error: {"success": false, "message": "...", "errors": {...}}
 */
class BaseController extends Controller
{
    /**
     * Send a successful JSON response.
     *
     * @param mixed $data Response data
     * @param string $message Status message
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function success($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()
            ->json($payload, $status)
            ->header('Content-Type', 'application/json')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Send an error JSON response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array|null $errors Validation errors or additional details
     * @return JsonResponse
     */
    protected function error(string $message, int $status = 422, $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()
            ->json($payload, $status)
            ->header('Content-Type', 'application/json')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Send a 404 Not Found response.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Send a 403 Forbidden response.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Send a 401 Unauthorized response.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Send a 400 Bad Request response.
     *
     * @param string $message
     * @param array|null $errors
     * @return JsonResponse
     */
    protected function badRequest(string $message = 'Bad request', $errors = null): JsonResponse
    {
        return $this->error($message, 400, $errors);
    }

    /**
     * Send a 409 Conflict response.
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function conflict(string $message = 'Conflict'): JsonResponse
    {
        return $this->error($message, 409);
    }

    /**
     * Send a 422 Unprocessable Entity response (validation error).
     *
     * @param array $errors Validation errors
     * @param string $message
     * @return JsonResponse
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }
}
