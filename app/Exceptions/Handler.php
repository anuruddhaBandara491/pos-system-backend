<?php

namespace App\Exceptions;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Custom exception handlers
        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $this->logSecurityEvent($request, 'validation_error', 422);

                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $e->errors(),
                    'status' => 422,
                ], 422);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $this->logSecurityEvent($request, 'not_found', 404);

                return response()->json([
                    'success' => false,
                    'error' => 'Resource not found',
                    'status' => 404,
                ], 404);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                $this->logSecurityEvent($request, 'model_not_found', 404);

                return response()->json([
                    'success' => false,
                    'error' => 'Resource not found',
                    'status' => 404,
                ], 404);
            }
        });

        $this->renderable(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = $e->getStatusCode();
                $message = match ($statusCode) {
                    400 => 'Bad request',
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Not found',
                    405 => 'Method not allowed',
                    409 => 'Conflict',
                    410 => 'Gone',
                    429 => 'Too many requests',
                    500 => 'Internal server error',
                    501 => 'Not implemented',
                    502 => 'Bad gateway',
                    503 => 'Service unavailable',
                    default => $e->getMessage() ?? 'An error occurred',
                };

                $this->logSecurityEvent($request, 'http_exception', $statusCode);

                return response()->json([
                    'success' => false,
                    'error' => $message,
                    'status' => $statusCode,
                ], $statusCode);
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                // Log unexpected exceptions
                \Log::error('Unhandled exception', [
                    'exception' => $e,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);

                $this->logSecurityEvent($request, 'unhandled_exception', 500);

                // Don't expose sensitive error details in production
                $message = app()->environment('production')
                    ? 'An internal server error occurred'
                    : $e->getMessage();

                return response()->json([
                    'success' => false,
                    'error' => $message,
                    'status' => 500,
                ], 500);
            }
        });
    }

    /**
     * Log security-related exception events.
     */
    private function logSecurityEvent(Request $request, string $action, int $statusCode): void
    {
        try {
            AuditLog::logAction($action, [
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_code' => $statusCode,
                'details' => [
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                ],
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't interrupt error handling
            \Log::warning('Exception event logging failed: '.$e->getMessage());
        }
    }
}
