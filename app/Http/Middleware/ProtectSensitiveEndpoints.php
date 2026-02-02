<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSensitiveEndpoints
{
    /**
     * Handle an incoming request.
     *
     * Adds extra security checks for sensitive operations:
     * - Refund operations require explicit confirmation
     * - User deactivation requires proper authorization
     * - Stock adjustments require manager+ role
     * - Logs all sensitive operations
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check for refund operations
        if ($request->is('api/v1/orders/*/payments/refund')) {
            if (!$request->has('confirm') || $request->input('confirm') !== true) {
                return response()->json([
                    'success' => false,
                    'error' => 'Refund requires explicit confirmation. Include "confirm": true in request body.',
                    'status' => 400,
                ], 400);
            }

            if (!$user->hasRole(['manager', 'admin'])) {
                $this->logSecurityEvent($request, 'refund_denied', 'Insufficient permissions');
                return response()->json([
                    'success' => false,
                    'error' => 'Only managers can process refunds.',
                    'status' => 403,
                ], 403);
            }
        }

        // Check for user deactivation
        if ($request->is('api/v1/users/*/deactivate')) {
            if (!$user->hasRole(['manager', 'admin'])) {
                $this->logSecurityEvent($request, 'deactivation_denied', 'Insufficient permissions');
                return response()->json([
                    'success' => false,
                    'error' => 'Only managers can deactivate users.',
                    'status' => 403,
                ], 403);
            }

            // Log deactivation attempt
            $this->logSecurityEvent($request, 'user_deactivation_attempt', 'Deactivation initiated');
        }

        // Check for stock adjustments
        if ($request->is('api/v1/products/*/adjust-stock')) {
            if (!$user->hasRole(['manager', 'admin'])) {
                $this->logSecurityEvent($request, 'stock_adjustment_denied', 'Insufficient permissions');
                return response()->json([
                    'success' => false,
                    'error' => 'Only managers can adjust stock.',
                    'status' => 403,
                ], 403);
            }

            // Validate stock adjustment amount
            $quantity = $request->input('quantity');
            if (!is_numeric($quantity)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid quantity format.',
                    'status' => 400,
                ], 400);
            }

            // Log adjustment
            $this->logSecurityEvent($request, 'stock_adjustment_attempt', [
                'quantity' => $quantity,
                'reason' => $request->input('reason'),
            ]);
        }

        // Check for user creation with role assignment
        if ($request->is('api/v1/users') && $request->method() === 'POST') {
            if (!$user->hasRole(['manager', 'admin'])) {
                $this->logSecurityEvent($request, 'user_creation_denied', 'Insufficient permissions');
                return response()->json([
                    'success' => false,
                    'error' => 'Only managers can create users.',
                    'status' => 403,
                ], 403);
            }

            // Validate role assignment
            $role = $request->input('role');
            if ($role && !in_array($role, ['cashier', 'manager', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid role specified.',
                    'status' => 400,
                ], 400);
            }

            // Admin role can only be assigned by admin
            if ($role === 'admin' && !$user->hasRole('admin')) {
                $this->logSecurityEvent($request, 'admin_role_creation_denied', 'Only admins can create admin users');
                return response()->json([
                    'success' => false,
                    'error' => 'Only admins can assign admin role.',
                    'status' => 403,
                ], 403);
            }
        }

        // Check for product deletion (requires admin)
        if ($request->is('api/v1/products/*') && $request->method() === 'DELETE') {
            if (!$user->hasRole('admin')) {
                $this->logSecurityEvent($request, 'product_deletion_denied', 'Only admin can delete products');
                return response()->json([
                    'success' => false,
                    'error' => 'Only admins can delete products.',
                    'status' => 403,
                ], 403);
            }

            $this->logSecurityEvent($request, 'product_deletion_attempt', 'Product deletion initiated');
        }

        // Check for branch deletion (requires admin)
        if ($request->is('api/v1/branches/*') && $request->method() === 'DELETE') {
            if (!$user->hasRole('admin')) {
                $this->logSecurityEvent($request, 'branch_deletion_denied', 'Only admin can delete branches');
                return response()->json([
                    'success' => false,
                    'error' => 'Only admins can delete branches.',
                    'status' => 403,
                ], 403);
            }

            $this->logSecurityEvent($request, 'branch_deletion_attempt', 'Branch deletion initiated');
        }

        return $next($request);
    }

    /**
     * Log security events (access denials, sensitive operations, etc.).
     */
    private function logSecurityEvent(Request $request, string $action, $details = null): void
    {
        try {
            AuditLog::logAction($action, [
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_code' => 403,
                'details' => is_array($details) ? $details : ['reason' => $details],
            ]);
        } catch (\Exception $e) {
            \Log::warning('Security event logging failed: '.$e->getMessage());
        }
    }
}
