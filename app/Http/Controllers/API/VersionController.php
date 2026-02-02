<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VersionController extends BaseController
{
    /**
     * Get API version and build information.
     *
     * Used by Electron app for:
     * - Version display
     * - Update checks
     * - Compatibility validation
     *
     * @return JsonResponse
     */
    public function current(): JsonResponse
    {
        $version = [
            'api' => config('app.version', '1.0.0'),
            'name' => config('app.name', 'POS System'),
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($version)
            ->header('X-Version', $version['api']);
    }

    /**
     * Get detailed version information with build metadata.
     *
     * Includes:
     * - API version
     * - Laravel version
     * - PHP version
     * - Database version
     * - Supported client versions
     *
     * @return JsonResponse
     */
    public function detailed(): JsonResponse
    {
        $version = [
            'api' => [
                'version' => config('app.version', '1.0.0'),
                'name' => config('app.name', 'POS System'),
                'release_date' => config('app.release_date', '2026-01-28'),
                'build' => config('app.build', 'dev'),
            ],
            'framework' => [
                'laravel' => \Illuminate\Foundation\Application::VERSION,
                'php' => phpversion(),
            ],
            'database' => [
                'version' => $this->getDatabaseVersion(),
                'driver' => config('database.default'),
            ],
            'features' => [
                'api_v1' => true,
                'websockets' => false,
                'authentication' => 'sanctum',
                'rate_limiting' => true,
                'audit_logging' => true,
            ],
            'supported_clients' => [
                'electron' => '>=1.0.0',
                'web' => '>=1.0.0',
                'mobile' => '>=1.0.0',
            ],
            'endpoints' => [
                'api' => '/api/v1',
                'health' => '/health',
                'version' => '/version',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($version)
            ->header('X-Version', $version['api']['version']);
    }

    /**
     * Check if a client version is compatible.
     *
     * Query Parameters:
     * - client: client type (electron, web, mobile)
     * - version: client version to check
     *
     * Returns compatibility status and upgrade recommendations.
     *
     * @return JsonResponse
     */
    public function checkCompatibility(): JsonResponse
    {
        $client = request()->query('client', 'unknown');
        $clientVersion = request()->query('version', '0.0.0');

        // Define minimum required versions
        $requirements = [
            'electron' => '1.0.0',
            'web' => '1.0.0',
            'mobile' => '1.0.0',
        ];

        // Check if client type is supported
        if (!isset($requirements[$client])) {
            return response()->json([
                'compatible' => false,
                'error' => "Unknown client type: {$client}",
                'supported_clients' => array_keys($requirements),
            ], 400);
        }

        // Compare versions
        $requiredVersion = $requirements[$client];
        $compatible = version_compare($clientVersion, $requiredVersion, '>=');

        $response = [
            'compatible' => $compatible,
            'client' => $client,
            'client_version' => $clientVersion,
            'required_version' => $requiredVersion,
            'api_version' => config('app.version', '1.0.0'),
            'timestamp' => now()->toIso8601String(),
        ];

        if (!$compatible) {
            $response['message'] = "Minimum {$client} version {$requiredVersion} is required";
            $response['upgrade_required'] = true;
        } else {
            $response['message'] = 'Compatible';
            $response['upgrade_required'] = false;
        }

        return response()->json($response, $compatible ? 200 : 426); // 426 = Upgrade Required
    }

    /**
     * Get changelog/release notes.
     *
     * Query Parameters:
     * - limit: number of releases to return (default: 10)
     * - version: specific version to fetch
     *
     * @return JsonResponse
     */
    public function changelog(): JsonResponse
    {
        $limit = (int) request()->query('limit', 10);
        $requestedVersion = request()->query('version');

        // Define changelog (in production, load from file or database)
        $changelog = [
            [
                'version' => '1.0.0',
                'release_date' => '2026-01-28',
                'status' => 'current',
                'changes' => [
                    'features' => [
                        'Complete POS API implementation',
                        'Rate limiting and audit logging',
                        'Electron desktop integration',
                    ],
                    'bugfixes' => [
                        'CORS configuration for desktop apps',
                        'Token expiration for long-running sessions',
                    ],
                    'security' => [
                        'API rate limiting',
                        'Comprehensive audit logging',
                        'Sensitive endpoint protection',
                    ],
                ],
            ],
        ];

        // Filter by specific version if requested
        if ($requestedVersion) {
            $changelog = array_filter($changelog, function ($item) use ($requestedVersion) {
                return $item['version'] === $requestedVersion;
            });

            if (empty($changelog)) {
                return response()->json([
                    'error' => "Version {$requestedVersion} not found",
                ], 404);
            }
        }

        // Limit results
        $changelog = array_slice($changelog, 0, $limit);

        return response()->json([
            'releases' => $changelog,
            'total' => count($changelog),
            'limit' => $limit,
        ]);
    }

    /**
     * Get database version.
     */
    private function getDatabaseVersion(): string
    {
        try {
            $pdo = DB::connection()->getPdo();
            return $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
