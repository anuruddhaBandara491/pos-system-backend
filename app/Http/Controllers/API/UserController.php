<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Http\Requests\CreateCashierRequest;
use App\Http\Requests\ToggleUserStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * User management controller for POS API.
 * Handles CRUD operations for users (primarily cashiers).
 * Only managers and admins can access these endpoints.
 */
class UserController extends BaseController
{
    /**
     * Create a new cashier user.
     * Assigns the 'cashier' role on creation.
     */
    public function store(CreateCashierRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            // Assign cashier role
            $user->assignRole('cashier');

            DB::commit();

            return $this->success(
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => $user->getRoleNames(),
                ],
                'Cashier created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error(
                'Failed to create cashier: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * List all users with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with('roles');

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by role
            if ($request->has('role')) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->input('role'));
                });
            }

            // Search by name or email
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->integer('per_page', 15);
            $users = $query->paginate($perPage);

            return $this->success(
                [
                    'data' => $users->items(),
                    'pagination' => [
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                    ],
                ],
                'Users retrieved successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to retrieve users: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a single user by ID.
     */
    public function show(User $user): JsonResponse
    {
        try {
            return $this->success(
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getPermissionNames(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'User retrieved successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to retrieve user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Activate a user.
     */
    public function activate(User $user, ToggleUserStatusRequest $request): JsonResponse
    {
        try {
            $user->update(['is_active' => true]);

            return $this->success(
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_active' => $user->is_active,
                ],
                'User activated successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to activate user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Deactivate a user.
     */
    public function deactivate(User $user, ToggleUserStatusRequest $request): JsonResponse
    {
        try {
            // Prevent deactivating yourself
            if ($user->id === auth('api')->user()?->id()) {
                return $this->error(
                    'You cannot deactivate your own account',
                    422
                );
            }

            $user->update(['is_active' => false]);

            // Revoke all tokens for deactivated user
            $user->tokens()->delete();

            return $this->success(
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_active' => $user->is_active,
                ],
                'User deactivated successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to deactivate user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a user (soft delete via deactivation).
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Prevent deleting yourself
            if ($user->id === auth('api')->user()?->id()) {
                return $this->error(
                    'You cannot delete your own account',
                    422
                );
            }

            // Soft delete via deactivation and token revocation
            $user->update(['is_active' => false]);
            $user->tokens()->delete();

            return $this->success(
                null,
                'User deleted successfully',
                200
            );
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to delete user: ' . $e->getMessage(),
                500
            );
        }
    }
}
