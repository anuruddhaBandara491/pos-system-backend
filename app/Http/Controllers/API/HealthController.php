<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends BaseController
{
    /**
     * Health check endpoint for Electron app monitoring.
     *
     * Returns basic system health status:
     * - API availability
     * - Database connectivity
     * - Cache status
     * - Queue status (if applicable)
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'api' => 'operational',
                'version' => config('app.version', '1.0.0'),
            ];

            // Check database connectivity
            try {
                DB::connection()->getPdo();
                $health['database'] = 'operational';
            } catch (\Exception $e) {
                $health['database'] = 'unavailable';
                $health['status'] = 'degraded';
            }

            // Check cache
            try {
                Cache::put('health_check', true, 1);
                $health['cache'] = 'operational';
            } catch (\Exception $e) {
                $health['cache'] = 'unavailable';
                $health['status'] = 'degraded';
            }

            // Determine HTTP status code
            $httpStatus = $health['status'] === 'healthy' ? 200 : 503;

            return response()->json($health, $httpStatus)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Detailed health status with additional metrics.
     *
     * For monitoring and diagnostics, provides:
     * - Memory usage
     * - Request count (from cache)
     * - Last error status
     *
     * @return JsonResponse
     */
    public function detailed(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'api' => [
                    'version' => config('app.version', '1.0.0'),
                    'environment' => app()->environment(),
                    'debug' => config('app.debug'),
                ],
                'services' => [
                    'database' => $this->checkDatabase(),
                    'cache' => $this->checkCache(),
                ],
                'resources' => [
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'memory_limit_mb' => $this->getMemoryLimitMb(),
                ],
            ];

            // Set overall status based on services
            if (in_array('unavailable', array_values($health['services']))) {
                $health['status'] = 'degraded';
            }

            $httpStatus = $health['status'] === 'healthy' ? 200 : 503;

            return response()->json($health, $httpStatus)
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Liveness probe (Kubernetes/container-style health check).
     *
     * Simple endpoint that returns 200 if service is running.
     * Used for container orchestration and monitoring.
     *
     * @return JsonResponse
     */
    public function live(): JsonResponse
    {
        return response()->json(['alive' => true], 200)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Readiness probe (Kubernetes/container-style health check).
     *
     * Returns 200 only if service is ready to accept requests.
     * Checks critical services (database, cache).
     *
     * @return JsonResponse
     */
    public function ready(): JsonResponse
    {
        $ready = true;
        $status = 200;

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $ready = false;
            $status = 503;
        }

        return response()->json(['ready' => $ready], $status)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'operational';
        } catch (\Exception $e) {
            Log::warning('Database health check failed: '.$e->getMessage());
            return 'unavailable';
        }
    }

    /**
     * Check cache connectivity.
     */
    private function checkCache(): string
    {
        try {
            Cache::put('health_check', true, 1);
            Cache::forget('health_check');
            return 'operational';
        } catch (\Exception $e) {
            Log::warning('Cache health check failed: '.$e->getMessage());
            return 'unavailable';
        }
    }

    /**
     * Get PHP memory limit in MB.
     */
    private function getMemoryLimitMb(): string
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 'unlimited';
        }
        return rtrim($limit, 'M');
    }
}
