<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends BaseController
{
    /**
     * Get list of all branches with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Branch::query();

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search by name or code
            if ($request->has('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%")
                        ->orWhere('city', 'ilike', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->string('sort_by', 'created_at');
            $sortOrder = $request->string('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $branches = $query->paginate($request->integer('per_page', 15));

            return $this->success(
                [
                    'data' => $branches->items(),
                    'pagination' => [
                        'total' => $branches->total(),
                        'per_page' => $branches->perPage(),
                        'current_page' => $branches->currentPage(),
                        'last_page' => $branches->lastPage(),
                    ],
                ],
                'Branches retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve branches: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get single branch details.
     */
    public function show(Branch $branch): JsonResponse
    {
        try {
            $branchData = $branch->load('users:id,name,email,branch_id');

            return $this->success(
                [
                    'id' => $branchData->id,
                    'name' => $branchData->name,
                    'code' => $branchData->code,
                    'address' => $branchData->address,
                    'city' => $branchData->city,
                    'state' => $branchData->state,
                    'phone' => $branchData->phone,
                    'email' => $branchData->email,
                    'is_active' => $branchData->is_active,
                    'user_count' => $branchData->users->count(),
                    'users' => $branchData->users,
                    'created_at' => $branchData->created_at,
                    'updated_at' => $branchData->updated_at,
                ],
                'Branch retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve branch: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create new branch.
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $branch = Branch::create($validated);

            DB::commit();

            return $this->success(
                [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'address' => $branch->address,
                    'city' => $branch->city,
                    'state' => $branch->state,
                    'phone' => $branch->phone,
                    'email' => $branch->email,
                    'is_active' => $branch->is_active,
                    'created_at' => $branch->created_at,
                    'updated_at' => $branch->updated_at,
                ],
                'Branch created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create branch: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update branch details.
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $branch->update($validated);

            DB::commit();

            return $this->success(
                [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'address' => $branch->address,
                    'city' => $branch->city,
                    'state' => $branch->state,
                    'phone' => $branch->phone,
                    'email' => $branch->email,
                    'is_active' => $branch->is_active,
                    'created_at' => $branch->created_at,
                    'updated_at' => $branch->updated_at,
                ],
                'Branch updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to update branch: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete branch.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Check if branch has active users
            $activeUsers = $branch->users()->where('is_active', true)->count();
            if ($activeUsers > 0) {
                return $this->error('Cannot delete branch with active users. Please reassign or deactivate users first.', 422);
            }

            $branchId = $branch->id;
            $branch->delete();

            DB::commit();

            return $this->success(
                ['id' => $branchId],
                'Branch deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to delete branch: '.$e->getMessage(), 500);
        }
    }
}
