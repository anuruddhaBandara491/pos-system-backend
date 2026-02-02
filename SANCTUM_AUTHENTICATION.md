# Laravel Sanctum Authentication for POS System
## Secure API Authentication & Token Management

---

## 1. Overview & Security Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                              │
├─────────────────────────────────────────────────────────────────┤
│ 1. HTTPS/TLS 1.3 (Transport)                                    │
│ 2. CORS Validation (Origin)                                     │
│ 3. Sanctum Tokens (Authentication)                              │
│ 4. Rate Limiting (DoS Protection)                               │
│ 5. Role-Based Access Control (Authorization)                    │
│ 6. Token Expiration & Rotation (Session Management)             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────┐                    ┌──────────────────┐
│  ELECTRON APP   │                    │  LARAVEL API     │
├─────────────────┤                    ├──────────────────┤
│ Credentials     │─── POST /login ───→│ Validate         │
│ + Email         │                    │ Hash password    │
│ + Password      │                    │ Generate token   │
│                 │←── Return token ───│ Return metadata  │
│                 │                    │                  │
│ Store in        │                    │                  │
│ OS Keychain     │                    │                  │
│                 │                    │                  │
│ Include in      │─── GET /api/* ────→│ Validate token   │
│ Authorization   │                    │ Check expiry     │
│ Header          │                    │ Check revoked    │
│                 │←── Return data ────│                  │
│                 │                    │                  │
│ Delete on       │─── POST /logout ──→│ Revoke token     │
│ logout          │                    │ Delete record    │
└─────────────────┘                    └──────────────────┘
```

---

## 2. Laravel Backend Setup

### 2.1 Install & Configure Sanctum

```bash
# Install Sanctum
composer require laravel/sanctum

# Publish configuration
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations
php artisan migrate
```

### 2.2 Configure Sanctum (config/sanctum.php)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    | Domains listed here will receive "stateful" authentication via Sanctum.
    | For Electron apps, these should be the IPs/domains where Electron runs.
    */
    'stateful' => explode(',', env(
        'SANCTUM_STATEFUL_DOMAINS',
        'localhost,127.0.0.1,localhost:3000,127.0.0.1:3000'
    )),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    | This defines the default guard configuration for API token validation.
    */
    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    | This is how many minutes an API token remains valid before expiring.
    | If null, tokens last indefinitely (recommended for desktop apps with rotation).
    */
    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    | API tokens will have this prefix to identify them easily.
    */
    'token_prefix' => 'pos_',

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

### 2.3 Update User Model (app/Models/User.php)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasOrganization;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasOrganization;

    protected $fillable = [
        'organization_id',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'role_id',
        'primary_branch_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    // Get all tokens for user
    public function tokens()
    {
        return $this->hasMany(\Laravel\Sanctum\PersonalAccessToken::class);
    }

    // Revoke all tokens
    public function revokeAllTokens()
    {
        return $this->tokens()->delete();
    }

    // Get active tokens
    public function activeTokens()
    {
        return $this->tokens()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where('revoked', false);
    }

    // Check if user can create tokens
    public function canCreateToken()
    {
        return $this->is_active && $this->email_verified_at;
    }
}
```

### 2.4 Login Request Validation (app/Http/Requests/Auth/LoginRequest.php)

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:255',
            'device_name' => 'required|string|max:255', // Desktop/Device identifier
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'device_name.required' => 'Device name is required',
        ];
    }
}
```

### 2.5 Logout Request Validation (app/Http/Requests/Auth/LogoutRequest.php)

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'revoke_all' => 'boolean', // Revoke all tokens or just current?
        ];
    }
}
```

### 2.6 Auth Service (app/Services/AuthService.php)

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\AuthenticationException;
use Exception;

class AuthService
{
    /**
     * Authenticate user and create API token
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        try {
            // Find user
            $user = User::where('email', $email)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                Log::warning("Failed login attempt for email: {$email}");
                throw new AuthenticationException('Invalid credentials');
            }

            // Check if user is active
            if (!$user->is_active) {
                Log::warning("Inactive user login attempt: {$email}");
                throw new AuthenticationException('Your account is inactive');
            }

            // Create token with metadata
            $token = $user->createToken(
                $deviceName,
                ['*'], // Abilities/Scopes
                now()->addDays(30) // Expiration (optional)
            );

            // Log login attempt
            Log::info("User {$email} logged in from device: {$deviceName}");

            return [
                'success' => true,
                'token' => $token->plainTextToken,
                'user' => $this->formatUserResponse($user),
                'metadata' => [
                    'token_expires_at' => $token->accessToken->expires_at,
                    'device_name' => $deviceName,
                    'created_at' => $token->accessToken->created_at,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Login error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revoke current token
     */
    public function logout(User $user, bool $revokeAll = false): bool
    {
        try {
            if ($revokeAll) {
                // Revoke all tokens for this user
                $user->revokeAllTokens();
                Log::info("User {$user->email} revoked all tokens");
            } else {
                // Revoke current token only
                $user->currentAccessToken()->delete();
                Log::info("User {$user->email} revoked current token");
            }

            return true;
        } catch (Exception $e) {
            Log::error("Logout error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Refresh token (revoke old, create new)
     */
    public function refreshToken(User $user, string $deviceName): array
    {
        try {
            // Get device name from old token
            $oldToken = $user->currentAccessToken();
            $device = $oldToken->name ?? $deviceName;

            // Delete old token
            $oldToken->delete();

            // Create new token
            $newToken = $user->createToken(
                $device,
                ['*'],
                now()->addDays(30)
            );

            Log::info("User {$user->email} token refreshed on device: {$device}");

            return [
                'success' => true,
                'token' => $newToken->plainTextToken,
                'metadata' => [
                    'token_expires_at' => $newToken->accessToken->expires_at,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Token refresh error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate token is not expired and not revoked
     */
    public function validateToken(string $token): bool
    {
        try {
            $parts = explode('|', $token);
            if (count($parts) !== 2) {
                return false;
            }

            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if (!$token) {
                return false;
            }

            // Check expiration
            if ($token->expires_at && $token->expires_at->isPast()) {
                return false;
            }

            // Check revocation
            if ($token->revoked) {
                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error("Token validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format user response data
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'organization_id' => $user->organization_id,
            'primary_branch_id' => $user->primary_branch_id,
            'role' => $user->role?->name,
            'permissions' => $user->role?->permissions->pluck('permission_name')->toArray() ?? [],
            'is_active' => $user->is_active,
        ];
    }

    /**
     * Get all active sessions for user
     */
    public function getActiveSessions(User $user): array
    {
        return $user->activeTokens()
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'is_current' => auth()->user()?->currentAccessToken()?->id === $token->id,
                ];
            })
            ->toArray();
    }

    /**
     * Revoke specific session
     */
    public function revokeSession(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);
        
        if (!$token) {
            throw new Exception('Token not found');
        }

        $token->delete();
        Log::info("User {$user->email} revoked session: {$token->name}");

        return true;
    }
}
```

### 2.7 Auth Controller (app/Http/Controllers/Auth/AuthController.php)

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * POST /api/auth/login
     * Login with email and password, return API token
     */
    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request->device_name
            );

            return $this->success(
                $result,
                'Login successful',
                201
            );
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (\Exception $e) {
            Log::error('Login controller error: ' . $e->getMessage());
            return $this->error('An error occurred during login', 500);
        }
    }

    /**
     * POST /api/auth/logout
     * Logout and revoke token(s)
     */
    public function logout(LogoutRequest $request)
    {
        try {
            $this->authService->logout(
                auth()->user(),
                $request->boolean('revoke_all', false)
            );

            return $this->success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->error('Logout failed', 500);
        }
    }

    /**
     * GET /api/auth/me
     * Get current authenticated user
     */
    public function me()
    {
        return $this->success(
            new UserResource(auth()->user()),
            'User retrieved'
        );
    }

    /**
     * POST /api/auth/refresh
     * Refresh the current token
     */
    public function refresh()
    {
        try {
            $result = $this->authService->refreshToken(
                auth()->user(),
                $request->device_name ?? 'Unknown Device'
            );

            return $this->success($result, 'Token refreshed');
        } catch (\Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            return $this->error('Failed to refresh token', 500);
        }
    }

    /**
     * GET /api/auth/sessions
     * Get all active sessions for current user
     */
    public function getSessions()
    {
        try {
            $sessions = $this->authService->getActiveSessions(auth()->user());
            return $this->success($sessions, 'Sessions retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve sessions', 500);
        }
    }

    /**
     * DELETE /api/auth/sessions/{tokenId}
     * Revoke a specific session
     */
    public function revokeSession($tokenId)
    {
        try {
            $this->authService->revokeSession(auth()->user(), $tokenId);
            return $this->success(null, 'Session revoked');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/auth/verify-token
     * Verify if token is still valid (for offline sync)
     */
    public function verifyToken()
    {
        try {
            $token = request()->bearerToken();
            $isValid = $this->authService->validateToken($token);

            return $this->success([
                'is_valid' => $isValid,
                'user' => auth()->user() ? new UserResource(auth()->user()) : null,
            ]);
        } catch (\Exception $e) {
            return $this->error('Token verification failed', 401);
        }
    }
}
```

### 2.8 Configure Routes (routes/api.php)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

// Public auth routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/sessions', [AuthController::class, 'getSessions']);
    Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    Route::post('/verify-token', [AuthController::class, 'verifyToken']);
});

// All other API routes require authentication
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Products, Orders, Stock, etc.
    Route::apiResource('products', \App\Http\Controllers\Products\ProductController::class);
    Route::apiResource('orders', \App\Http\Controllers\Orders\OrderController::class);
    // ... other routes
});
```

### 2.9 Custom Middleware (app/Http/Middleware/CheckTokenExpiry.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $token = auth()->user()->currentAccessToken();

            // Check if token is expired
            if ($token && $token->expires_at && $token->expires_at->isPast()) {
                auth()->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Token has expired. Please login again.',
                ], 401);
            }

            // Check if token is revoked
            if ($token && $token->revoked) {
                auth()->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Token has been revoked. Please login again.',
                ], 401);
            }

            // Update last used at
            $token?->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
```

### 2.10 Authorization Middleware (app/Http/Middleware/CheckPermission.php)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $userPermissions = $user->role?->permissions->pluck('permission_name')->toArray() ?? [];

        // Check if user has at least one of the required permissions
        $hasPermission = collect($permissions)->intersect($userPermissions)->count() > 0;

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }

        return $next($request);
    }
}
```

### 2.11 Rate Limiting (config/logging.php - Add to kernel)

```php
// In app/Http/Kernel.php

protected $routeMiddleware = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'check.token.expiry' => \App\Http\Middleware\CheckTokenExpiry::class,
    'permission' => \App\Http\Middleware\CheckPermission::class,
];

// In routes/api.php
Route::middleware([
    'throttle:60,1', // 60 requests per minute
    'check.token.expiry',
])->group(function () {
    // Routes
});
```

---

## 3. Electron Desktop App - Secure Token Management

### 3.1 Token Manager (electron/main/auth/TokenManager.ts)

```typescript
import keytar from 'keytar';
import { safeStorage } from 'electron';
import * as fs from 'fs';
import * as path from 'path';

const SERVICE_NAME = 'pos-app';
const ACCOUNT_NAME = 'auth_token';
const TOKEN_FILE = path.join(process.env.APPDATA || process.env.HOME || '', '.pos-app', 'auth.json');

export class TokenManager {
  /**
   * Save token securely in OS keychain
   */
  static async saveToken(token: string): Promise<void> {
    try {
      // Store in OS keychain (most secure)
      await keytar.setPassword(SERVICE_NAME, ACCOUNT_NAME, token);
      console.log('Token saved to keychain');
    } catch (error) {
      console.error('Failed to save token to keychain:', error);
      // Fallback: Store encrypted on disk (Windows may not have keytar)
      this.saveFallbackToken(token);
    }
  }

  /**
   * Retrieve token from OS keychain
   */
  static async getToken(): Promise<string | null> {
    try {
      const token = await keytar.getPassword(SERVICE_NAME, ACCOUNT_NAME);
      if (token) {
        return token;
      }
      // Fallback: Try to retrieve from encrypted file
      return this.retrieveFallbackToken();
    } catch (error) {
      console.error('Failed to retrieve token from keychain:', error);
      return this.retrieveFallbackToken();
    }
  }

  /**
   * Delete token from keychain
   */
  static async deleteToken(): Promise<void> {
    try {
      await keytar.deletePassword(SERVICE_NAME, ACCOUNT_NAME);
      console.log('Token deleted from keychain');
    } catch (error) {
      console.error('Failed to delete token from keychain:', error);
    }

    // Also delete fallback token
    this.deleteFallbackToken();
  }

  /**
   * Fallback: Encrypt and store token on disk
   */
  private static saveFallbackToken(token: string): void {
    try {
      const dir = path.dirname(TOKEN_FILE);
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }

      // Encrypt token using Electron's safeStorage
      const encrypted = safeStorage.encryptString(token);
      fs.writeFileSync(TOKEN_FILE, JSON.stringify({ token: encrypted }), {
        mode: 0o600, // Read/write for owner only
      });
    } catch (error) {
      console.error('Failed to save fallback token:', error);
    }
  }

  /**
   * Fallback: Retrieve encrypted token from disk
   */
  private static retrieveFallbackToken(): string | null {
    try {
      if (!fs.existsSync(TOKEN_FILE)) {
        return null;
      }

      const data = JSON.parse(fs.readFileSync(TOKEN_FILE, 'utf-8'));
      const decrypted = safeStorage.decryptString(data.token);
      return decrypted;
    } catch (error) {
      console.error('Failed to retrieve fallback token:', error);
      return null;
    }
  }

  /**
   * Delete fallback token file
   */
  private static deleteFallbackToken(): void {
    try {
      if (fs.existsSync(TOKEN_FILE)) {
        fs.unlinkSync(TOKEN_FILE);
      }
    } catch (error) {
      console.error('Failed to delete fallback token file:', error);
    }
  }

  /**
   * Check if token exists
   */
  static async hasToken(): Promise<boolean> {
    const token = await this.getToken();
    return !!token;
  }

  /**
   * Get token metadata
   */
  static async getTokenMetadata(): Promise<any> {
    try {
      const metadataFile = path.join(
        path.dirname(TOKEN_FILE),
        'auth-metadata.json'
      );

      if (fs.existsSync(metadataFile)) {
        return JSON.parse(fs.readFileSync(metadataFile, 'utf-8'));
      }
      return null;
    } catch (error) {
      console.error('Failed to read token metadata:', error);
      return null;
    }
  }

  /**
   * Save token metadata
   */
  static async saveTokenMetadata(metadata: any): Promise<void> {
    try {
      const metadataFile = path.join(
        path.dirname(TOKEN_FILE),
        'auth-metadata.json'
      );

      const dir = path.dirname(metadataFile);
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }

      fs.writeFileSync(metadataFile, JSON.stringify(metadata), {
        mode: 0o600,
      });
    } catch (error) {
      console.error('Failed to save token metadata:', error);
    }
  }

  /**
   * Check if token is expired
   */
  static async isTokenExpired(): Promise<boolean> {
    try {
      const metadata = await this.getTokenMetadata();
      if (!metadata || !metadata.expires_at) {
        return false; // No expiry info, assume valid
      }

      const expiryTime = new Date(metadata.expires_at);
      return expiryTime < new Date();
    } catch (error) {
      console.error('Failed to check token expiry:', error);
      return true;
    }
  }
}
```

### 3.2 API Client with Token Management (electron/main/api/ApiClient.ts)

```typescript
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';
import { TokenManager } from './TokenManager';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000';

export class ApiClient {
  private client: AxiosInstance;
  private token: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: `${API_BASE_URL}/api`,
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    // Add request interceptor to include token
    this.client.interceptors.request.use(
      async (config) => {
        const token = await TokenManager.getToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Add response interceptor to handle token expiry
    this.client.interceptors.response.use(
      (response) => response,
      async (error) => {
        const originalRequest = error.config;

        // Handle 401 Unauthorized
        if (error.response?.status === 401) {
          if (!originalRequest._retry) {
            originalRequest._retry = true;

            try {
              // Try to refresh token
              const refreshed = await this.refreshToken();
              if (refreshed) {
                // Retry original request
                return this.client(originalRequest);
              }
            } catch (refreshError) {
              console.error('Token refresh failed:', refreshError);
            }

            // If refresh fails, clear token and redirect to login
            await TokenManager.deleteToken();
            // Emit event for login page redirect (handle in main process)
            return Promise.reject(error);
          }
        }

        return Promise.reject(error);
      }
    );
  }

  /**
   * Login with email and password
   */
  async login(email: string, password: string, deviceName: string) {
    try {
      const response = await this.client.post('/auth/login', {
        email,
        password,
        device_name: deviceName,
      });

      const { data } = response;

      if (data.success && data.data.token) {
        // Save token securely
        await TokenManager.saveToken(data.data.token);

        // Save metadata
        await TokenManager.saveTokenMetadata({
          user: data.data.user,
          expires_at: data.data.metadata?.token_expires_at,
          created_at: new Date().toISOString(),
        });

        this.token = data.data.token;
        return data.data;
      }

      throw new Error(data.message || 'Login failed');
    } catch (error: any) {
      throw new Error(error.response?.data?.message || error.message);
    }
  }

  /**
   * Logout and revoke token
   */
  async logout(revokeAll: boolean = false) {
    try {
      await this.client.post('/auth/logout', {
        revoke_all: revokeAll,
      });
    } catch (error) {
      console.error('Logout API call failed:', error);
    } finally {
      // Always clear local token
      await TokenManager.deleteToken();
      this.token = null;
    }
  }

  /**
   * Refresh token
   */
  async refreshToken() {
    try {
      const response = await this.client.post('/auth/refresh', {
        device_name: 'POS Desktop',
      });

      const { data } = response;

      if (data.success && data.data.token) {
        // Save new token
        await TokenManager.saveToken(data.data.token);

        // Update metadata
        await TokenManager.saveTokenMetadata({
          expires_at: data.data.metadata?.token_expires_at,
          refreshed_at: new Date().toISOString(),
        });

        this.token = data.data.token;
        return true;
      }

      return false;
    } catch (error) {
      console.error('Token refresh failed:', error);
      return false;
    }
  }

  /**
   * Get current user
   */
  async getCurrentUser() {
    try {
      const response = await this.client.get('/auth/me');
      return response.data.data;
    } catch (error) {
      console.error('Failed to get current user:', error);
      throw error;
    }
  }

  /**
   * Verify token validity
   */
  async verifyToken() {
    try {
      const response = await this.client.post('/auth/verify-token');
      return response.data.data;
    } catch (error) {
      console.error('Token verification failed:', error);
      return { is_valid: false };
    }
  }

  /**
   * Generic GET request
   */
  async get(endpoint: string, config?: AxiosRequestConfig) {
    return this.client.get(endpoint, config);
  }

  /**
   * Generic POST request
   */
  async post(endpoint: string, data?: any, config?: AxiosRequestConfig) {
    return this.client.post(endpoint, data, config);
  }

  /**
   * Generic PUT request
   */
  async put(endpoint: string, data?: any, config?: AxiosRequestConfig) {
    return this.client.put(endpoint, data, config);
  }

  /**
   * Generic DELETE request
   */
  async delete(endpoint: string, config?: AxiosRequestConfig) {
    return this.client.delete(endpoint, config);
  }

  /**
   * Initialize client (check and restore token)
   */
  async initialize() {
    const hasToken = await TokenManager.hasToken();
    const isExpired = await TokenManager.isTokenExpired();

    if (hasToken && !isExpired) {
      this.token = await TokenManager.getToken();
      return true;
    }

    if (hasToken && isExpired) {
      // Try to refresh
      try {
        return await this.refreshToken();
      } catch (error) {
        console.error('Token refresh on init failed:', error);
        await TokenManager.deleteToken();
        return false;
      }
    }

    return false;
  }
}

export const apiClient = new ApiClient();
```

### 3.3 IPC Handlers (electron/main/ipc/authHandlers.ts)

```typescript
import { ipcMain } from 'electron';
import { apiClient } from '../api/ApiClient';
import { TokenManager } from '../auth/TokenManager';

export function setupAuthHandlers() {
  /**
   * IPC: Login
   */
  ipcMain.handle('auth:login', async (event, email: string, password: string) => {
    try {
      const result = await apiClient.login(email, password, 'POS Desktop');
      return {
        success: true,
        user: result.user,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });

  /**
   * IPC: Logout
   */
  ipcMain.handle('auth:logout', async (event, revokeAll: boolean = false) => {
    try {
      await apiClient.logout(revokeAll);
      return {
        success: true,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });

  /**
   * IPC: Get current user
   */
  ipcMain.handle('auth:get-current-user', async (event) => {
    try {
      const user = await apiClient.getCurrentUser();
      return {
        success: true,
        user,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });

  /**
   * IPC: Check if token exists
   */
  ipcMain.handle('auth:has-token', async (event) => {
    try {
      const hasToken = await TokenManager.hasToken();
      const isExpired = await TokenManager.isTokenExpired();

      return {
        success: true,
        has_token: hasToken,
        is_expired: isExpired,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });

  /**
   * IPC: Verify token
   */
  ipcMain.handle('auth:verify-token', async (event) => {
    try {
      const result = await apiClient.verifyToken();
      return {
        success: true,
        is_valid: result.is_valid,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });

  /**
   * IPC: Refresh token
   */
  ipcMain.handle('auth:refresh-token', async (event) => {
    try {
      const refreshed = await apiClient.refreshToken();
      return {
        success: refreshed,
      };
    } catch (error: any) {
      return {
        success: false,
        error: error.message,
      };
    }
  });
}
```

### 3.4 React Hook for Auth (electron/renderer/hooks/useAuth.ts)

```typescript
import { useState, useCallback, useEffect } from 'react';
import { useIpcRenderer } from './useIpcRenderer';

interface User {
  id: number;
  username: string;
  email: string;
  first_name: string;
  last_name: string;
  role: string;
  permissions: string[];
}

interface UseAuthReturn {
  isLoading: boolean;
  isAuthenticated: boolean;
  user: User | null;
  login: (email: string, password: string) => Promise<void>;
  logout: (revokeAll?: boolean) => Promise<void>;
  refresh: () => Promise<void>;
  verifyToken: () => Promise<boolean>;
}

export function useAuth(): UseAuthReturn {
  const { ipcRenderer } = useIpcRenderer();
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [user, setUser] = useState<User | null>(null);

  // Check authentication on mount
  useEffect(() => {
    checkAuthentication();
  }, []);

  const checkAuthentication = useCallback(async () => {
    setIsLoading(true);
    try {
      const result = await ipcRenderer.invoke('auth:has-token');

      if (result.success && result.has_token && !result.is_expired) {
        // Try to get current user
        const userResult = await ipcRenderer.invoke('auth:get-current-user');
        if (userResult.success) {
          setUser(userResult.user);
          setIsAuthenticated(true);
        }
      } else if (result.success && result.has_token && result.is_expired) {
        // Token expired, try to refresh
        const refreshResult = await ipcRenderer.invoke('auth:refresh-token');
        if (refreshResult.success) {
          await checkAuthentication(); // Retry recursively
        } else {
          setIsAuthenticated(false);
        }
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setIsAuthenticated(false);
    } finally {
      setIsLoading(false);
    }
  }, [ipcRenderer]);

  const login = useCallback(
    async (email: string, password: string) => {
      setIsLoading(true);
      try {
        const result = await ipcRenderer.invoke('auth:login', email, password);

        if (result.success) {
          setUser(result.user);
          setIsAuthenticated(true);
        } else {
          throw new Error(result.error || 'Login failed');
        }
      } finally {
        setIsLoading(false);
      }
    },
    [ipcRenderer]
  );

  const logout = useCallback(
    async (revokeAll: boolean = false) => {
      setIsLoading(true);
      try {
        const result = await ipcRenderer.invoke('auth:logout', revokeAll);

        if (result.success) {
          setUser(null);
          setIsAuthenticated(false);
        }
      } finally {
        setIsLoading(false);
      }
    },
    [ipcRenderer]
  );

  const refresh = useCallback(async () => {
    try {
      const result = await ipcRenderer.invoke('auth:refresh-token');

      if (result.success) {
        await checkAuthentication();
      }
    } catch (error) {
      console.error('Token refresh failed:', error);
    }
  }, [ipcRenderer, checkAuthentication]);

  const verifyToken = useCallback(async () => {
    try {
      const result = await ipcRenderer.invoke('auth:verify-token');
      return result.success && result.is_valid;
    } catch (error) {
      console.error('Token verification failed:', error);
      return false;
    }
  }, [ipcRenderer]);

  return {
    isLoading,
    isAuthenticated,
    user,
    login,
    logout,
    refresh,
    verifyToken,
  };
}
```

### 3.5 Login Page Component (electron/renderer/pages/Login.tsx)

```typescript
import React, { useState } from 'react';
import { useAuth } from '../hooks/useAuth';
import { useNavigate } from 'react-router-dom';

export const LoginPage: React.FC = () => {
  const { login, isLoading } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    try {
      await login(email, password);
      // Redirect to dashboard on success
      navigate('/dashboard');
    } catch (err: any) {
      setError(err.message || 'Login failed');
    }
  };

  return (
    <div className="login-container">
      <form onSubmit={handleSubmit}>
        <h1>POS System Login</h1>

        {error && <div className="error-message">{error}</div>}

        <div className="form-group">
          <label>Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            disabled={isLoading}
          />
        </div>

        <div className="form-group">
          <label>Password</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            disabled={isLoading}
          />
        </div>

        <button type="submit" disabled={isLoading}>
          {isLoading ? 'Logging in...' : 'Login'}
        </button>
      </form>
    </div>
  );
};
```

---

## 4. Security Best Practices

### 4.1 .env Configuration

```env
# Laravel Configuration
APP_ENV=production
APP_DEBUG=false
SANCTUM_EXPIRATION=10080  # 7 days in minutes

# CORS
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
CORS_ALLOWED_ORIGINS=http://localhost:3000

# Security Headers
SESSION_HTTP_ONLY=true
SESSION_SECURE=true
SESSION_SAME_SITE=lax

# API Security
API_RATE_LIMIT_PER_MINUTE=60
API_RATE_LIMIT_BURST=100

# File Upload Security
FILE_MAX_SIZE=5242880  # 5MB
ALLOWED_FILE_EXTENSIONS=jpg,jpeg,png,gif
```

### 4.2 Security Checklist

```
Authentication:
✓ Passwords hashed with bcrypt
✓ Tokens stored in OS keychain (not browser storage)
✓ Tokens have expiration dates
✓ Tokens can be revoked individually
✓ All tokens can be revoked at once
✓ Failed login attempts logged

Token Management:
✓ Bearer token in Authorization header
✓ Token rotation on refresh
✓ Token expiry checked on every request
✓ Revoked tokens rejected immediately
✓ Token metadata stored securely

Communication:
✓ HTTPS/TLS 1.3 enforced in production
✓ CORS configured for Electron origins only
✓ Rate limiting enabled
✓ Request logging enabled

Storage:
✓ Tokens in OS keychain (primary)
✓ Fallback encrypted storage on disk
✓ File permissions restricted (0o600)
✓ No tokens in localStorage or sessionStorage
✓ No plain text tokens anywhere

Session Management:
✓ Session timeout configured
✓ Device tracking enabled
✓ Multi-device session support
✓ Ability to revoke specific sessions
```

### 4.3 Logging & Monitoring

```php
// In app/Services/AuthService.php

Log::channel('auth')->info("User {$email} logged in", [
    'user_id' => $user->id,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'device_name' => $deviceName,
    'timestamp' => now(),
]);

Log::channel('auth')->warning("Failed login attempt for {$email}", [
    'ip_address' => request()->ip(),
    'timestamp' => now(),
]);

Log::channel('auth')->info("Token revoked for user {$user->email}", [
    'user_id' => $user->id,
    'token_id' => $token->id,
    'timestamp' => now(),
]);
```

### 4.4 Audit Trail Table

```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Log login
INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
VALUES (?, 'login', 'User logged in', ?, ?);

-- Log token refresh
INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
VALUES (?, 'token_refresh', 'Token refreshed', ?, ?);

-- Log logout
INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
VALUES (?, 'logout', 'User logged out', ?, ?);
```

---

## 5. Testing Authentication

### 5.1 Feature Test Example (tests/Feature/Auth/LoginTest.php)

```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginTest extends TestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    public function test_user_can_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => ['token', 'user', 'metadata'],
        ]);
        
        $this->assertNotNull($response->json('data.token'));
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_inactive_user_cannot_login()
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout()
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_revoked_token_cannot_access_protected_routes()
    {
        $token = $this->user->createToken('test')->plainTextToken;

        // Logout to revoke token
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        // Try to use revoked token
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_token_metadata_is_returned()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'metadata' => ['token_expires_at', 'device_name', 'created_at'],
            ],
        ]);
    }
}
```

---

## 6. Production Deployment Checklist

```
□ HTTPS/TLS certificate installed
□ APP_DEBUG=false in production .env
□ APP_ENV=production
□ Sanctum token expiration configured
□ Rate limiting enabled
□ CORS origins restricted
□ Failed login attempts logged
□ Token audit trail enabled
□ Keytar dependency available on target OS
□ Database backups automated
□ Error tracking (Sentry) configured
□ Monitoring alerts set up
□ Log aggregation configured
□ Security headers configured
□ API endpoint rate limits tested
□ Token refresh workflow tested
□ Multi-device session management tested
□ Token revocation tested
```

---

## Summary

✅ **Secure Token Storage**: OS keychain + encrypted fallback  
✅ **Token Management**: Creation, refresh, revocation, expiration  
✅ **Middleware**: Token expiry check, permission validation  
✅ **Audit Trail**: All auth actions logged  
✅ **Electron Integration**: IPC handlers, React hooks, API client  
✅ **Production Ready**: Error handling, rate limiting, monitoring  

Your POS system now has enterprise-grade authentication!

