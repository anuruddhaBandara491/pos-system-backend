<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CategoryController extends BaseController
{
    /**
     * Get list of all categories.
     */
    public function index(): JsonResponse
    {
        try {
            $categories = Category::all();

            return $this->success(
                $categories,
                'Categories retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $category = Category::create($validated);

            DB::commit();

            return $this->success(
                [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'created_at' => $category->created_at,
                ],
                'Category created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single category details.
     */
    public function show(Category $category): JsonResponse
    {
        try {
            return $this->success(
                [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ],
                'Category retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update category details.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $category->update($validated);

            DB::commit();

            return $this->success(
                [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'updated_at' => $category->updated_at,
                ],
                'Category updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        DB::beginTransaction();

        try {
            $categoryId = $category->id;
            $category->delete();

            DB::commit();

            return $this->success(
                ['id' => $categoryId],
                'Category deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
}
