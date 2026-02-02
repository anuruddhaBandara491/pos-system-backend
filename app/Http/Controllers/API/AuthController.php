<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentication controller for POS API.
 * Handles user login, logout, and current user retrieval using Sanctum tokens.
 */
class AuthController extends BaseController
{
    /**
     * Login with email and password.
     * Issues a personal access token for API authentication.
     */

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Find user by email
        $user = User::where('email', $validated['email'])->first();

        // Verify password
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        // Check if user is active/enabled
        if (isset($user->is_active) && ! $user->is_active) {
            return $this->error(
                'This account has been deactivated. Contact your administrator.',
                401
            );
        }

        // Create new personal access token
        $token = $user->createToken(
            'pos-api-token',
            ['*']
        );

        // Load roles and get all permissions (direct + role-based)
        $user->load('roles');
        $permissions = $user->getAllPermissions()->pluck('name')->values();
        
        return $this->success(
            [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'branch_id' => $user->branch_id,
                    'is_active' => $user->is_active,
                    // Important: include role names and permission names
                    'roles' => $user->roles->pluck('name')->values(),
                    'permissions' => $permissions,
                ],
            ],
            'Login successful',
            200
        );
    }

    /**
     * Logout (revoke token).
     * Requires authentication.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke all tokens for the authenticated user (all sessions)
        // or just the current token: $request->user()->currentAccessToken()->delete();

        $request->user()->tokens()->delete();

        return $this->success(
            null,
            'Logged out successfully',
            200
        );
    }

    /**
     * Get current authenticated user.
     * Requires valid Sanctum token.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated', 401);
        }

        // Load roles and get all permissions
        $user->load('roles');
        $permissions = $user->getAllPermissions()->pluck('name')->values();

        return $this->success(
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name')->values(),
                'permissions' => $permissions,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ],
            'User retrieved successfully',
            200
        );
    }
}
