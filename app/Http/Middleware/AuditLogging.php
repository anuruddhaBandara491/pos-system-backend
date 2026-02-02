<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogging
{
    /**
     * Handle an incoming request.
     *
     * Logs all requests to auditable endpoints for security tracking.
     * Actions logged:
     * - login/logout
     * - order creation/completion
     * - payment recording
     * - user management
     * - product changes
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log non-GET requests and successful operations
        if ($request->method() !== 'GET' && in_array($response->getStatusCode(), [200, 201, 202])) {
            $this->logAction($request, $response);
        }

        return $response;
    }

    /**
     * Log the action to audit_logs table.
     */
    private function logAction(Request $request, Response $response): void
    {
        try {
            $action = $this->getActionName($request);
            $entityData = $this->extractEntityData($request);

            AuditLog::logAction($action, [
                'entity_type' => $entityData['type'] ?? null,
                'entity_id' => $entityData['id'] ?? null,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_code' => $response->getStatusCode(),
                'details' => [
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                ],
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't interrupt request on logging error
            \Log::warning('Audit logging failed: '.$e->getMessage());
        }
    }

    /**
     * Determine the action name from the request.
     */
    private function getActionName(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        if (str_contains($path, 'auth/login')) {
            return 'login';
        }
        if (str_contains($path, 'auth/logout')) {
            return 'logout';
        }
        if (str_contains($path, 'orders') && str_contains($path, 'complete')) {
            return 'order_completed';
        }
        if (str_contains($path, 'orders') && $method === 'POST' && !str_contains($path, 'items')) {
            return 'order_created';
        }
        if (str_contains($path, 'orders') && str_contains($path, 'items') && $method === 'POST') {
            return 'order_item_added';
        }
        if (str_contains($path, 'payments') && $method === 'POST') {
            return 'payment_recorded';
        }
        if (str_contains($path, 'payments') && str_contains($path, 'refund')) {
            return 'payment_refunded';
        }
        if (str_contains($path, 'users') && $method === 'POST') {
            return 'user_created';
        }
        if (str_contains($path, 'users') && $method === 'PUT') {
            return 'user_updated';
        }
        if (str_contains($path, 'users') && str_contains($path, 'deactivate')) {
            return 'user_deactivated';
        }
        if (str_contains($path, 'products') && $method === 'POST') {
            return 'product_created';
        }
        if (str_contains($path, 'products') && $method === 'PUT') {
            return 'product_updated';
        }
        if (str_contains($path, 'products') && str_contains($path, 'adjust-stock')) {
            return 'product_stock_adjusted';
        }
        if (str_contains($path, 'branches') && $method === 'POST') {
            return 'branch_created';
        }
        if (str_contains($path, 'branches') && $method === 'PUT') {
            return 'branch_updated';
        }

        return strtolower($method).'_'.str_replace('/', '_', trim($path, '/'));
    }

    /**
     * Extract entity information from the request.
     */
    private function extractEntityData(Request $request): array
    {
        $path = $request->path();
        $segments = explode('/', $path);

        if (str_contains($path, 'orders')) {
            // Find order ID
            if (($key = array_search('orders', $segments)) !== false && isset($segments[$key + 1])) {
                return [
                    'type' => 'Order',
                    'id' => (int) $segments[$key + 1],
                ];
            }
        }
        if (str_contains($path, 'payments')) {
            if (($key = array_search('payments', $segments)) !== false && isset($segments[$key + 1])) {
                return [
                    'type' => 'Payment',
                    'id' => (int) $segments[$key + 1],
                ];
            }
        }
        if (str_contains($path, 'users')) {
            if (($key = array_search('users', $segments)) !== false && isset($segments[$key + 1])) {
                return [
                    'type' => 'User',
                    'id' => (int) $segments[$key + 1],
                ];
            }
        }
        if (str_contains($path, 'products')) {
            if (($key = array_search('products', $segments)) !== false && isset($segments[$key + 1])) {
                return [
                    'type' => 'Product',
                    'id' => (int) $segments[$key + 1],
                ];
            }
        }
        if (str_contains($path, 'branches')) {
            if (($key = array_search('branches', $segments)) !== false && isset($segments[$key + 1])) {
                return [
                    'type' => 'Branch',
                    'id' => (int) $segments[$key + 1],
                ];
            }
        }

        return [];
    }
}
