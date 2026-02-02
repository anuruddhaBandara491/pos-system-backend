<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseController
{
    /**
     * Get list of products with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Product::query();

            // Filter by branch if user is restricted to a branch
            if ($user && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($request->has('branch_id')) {
                // Allow filtering by branch_id if admin/manager
                $query->where('branch_id', $request->integer('branch_id'));
            }
            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->string('category'));
            }

            // Search by name, sku, or description
            if ($request->has('search')) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Barcode/SKU exact search
            if ($request->has('barcode')) {
                $query->where('sku', $request->string('barcode'));
            }

            // Filter low stock items
            if ($request->boolean('low_stock')) {
                $query->whereColumn('stock_qty', '<=', 'reorder_level');
            }
            // Sorting
            $sortBy = $request->string('sort_by', 'created_at');
            $sortOrder = $request->string('sort_order', 'desc');
            $allowedSortFields = ['name', 'sku', 'price', 'stock_qty', 'created_at'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            $query->orderBy($sortBy, $sortOrder);

            $products = $query->with('branch')->paginate(15);
            return $this->success(
                [
                    'data' => $products->items(),
                    'pagination' => [
                        'total' => $products->total(),
                        'per_page' => $products->perPage(),
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                    ],
                ],
                'Products retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve products: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get single product details.
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Check if user is restricted to a branch
            if ($user && $user->branch_id && $product->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this product', 403);
            }

            $productData = $product->load('branch');

            return $this->success(
                [
                    'id' => $productData->id,
                    'branch_id' => $productData->branch_id,
                    'branch' => $productData->branch ? [
                        'id' => $productData->branch->id,
                        'name' => $productData->branch->name,
                        'code' => $productData->branch->code,
                    ] : null,
                    'sku' => $productData->sku,
                    'name' => $productData->name,
                    'description' => $productData->description,
                    'price' => $productData->price,
                    'cost' => $productData->cost,
                    'profit_margin' => round($productData->profit_margin, 2),
                    'stock_qty' => $productData->stock_qty,
                    'reorder_level' => $productData->reorder_level,
                    'is_low_stock' => $productData->isLowStock(),
                    'category' => $productData->category,
                    'is_active' => $productData->is_active,
                    'created_at' => $productData->created_at,
                    'updated_at' => $productData->updated_at,
                ],
                'Product retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create new product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $product = Product::create($validated);
            $product->load('branch');

            DB::commit();

            return $this->success(
                [
                    'id' => $product->id,
                    'branch_id' => $product->branch_id,
                    'branch' => $product->branch ? [
                        'id' => $product->branch->id,
                        'name' => $product->branch->name,
                        'code' => $product->branch->code,
                    ] : null,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'cost' => $product->cost,
                    'profit_margin' => round($product->profit_margin, 2),
                    'stock_qty' => $product->stock_qty,
                    'reorder_level' => $product->reorder_level,
                    'category' => $product->category,
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at,
                ],
                'Product created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Update product details.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = auth('api')->user();

            // Check if user is restricted to a branch
            if ($user && $user->branch_id && $product->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this product', 403);
            }

            $validated = $request->validated();
            $product->update($validated);
            $product->load('branch');

            DB::commit();

            return $this->success(
                [
                    'id' => $product->id,
                    'branch_id' => $product->branch_id,
                    'branch' => $product->branch ? [
                        'id' => $product->branch->id,
                        'name' => $product->branch->name,
                        'code' => $product->branch->code,
                    ] : null,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'cost' => $product->cost,
                    'profit_margin' => round($product->profit_margin, 2),
                    'stock_qty' => $product->stock_qty,
                    'reorder_level' => $product->reorder_level,
                    'category' => $product->category,
                    'is_active' => $product->is_active,
                    'updated_at' => $product->updated_at,
                ],
                'Product updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to update product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Delete product.
     */
    public function destroy(Product $product): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = auth('api')->user();

            // Check if user is restricted to a branch
            if ($user && $user->branch_id && $product->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this product', 403);
            }

            $productId = $product->id;
            $product->delete();

            DB::commit();

            return $this->success(
                ['id' => $productId],
                'Product deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to delete product: '.$e->getMessage(), 500);
        }
    }

    /**
     * Adjust product stock by quantity (increase or decrease).
     */
    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = auth('api')->user();

            // Check authorization
            if (! $user->hasAnyRole(['manager', 'admin'])) {
                return $this->error('Unauthorized', 403);
            }

            // Check if user is restricted to a branch
            if ($user->branch_id && $product->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this product', 403);
            }

            $request->validate([
                'quantity' => 'required|integer|not_in:0',
                'reason' => 'nullable|string|max:255',
            ]);

            $quantity = $request->integer('quantity');
            $newStock = $product->stock_qty + $quantity;

            if ($newStock < 0) {
                return $this->error('Insufficient stock. Cannot reduce below 0.', 422);
            }

            $product->update(['stock_qty' => $newStock]);

            // Record stock movement
            StockMovement::record(
                $product->branch_id,
                $product->id,
                'adjustment',
                $quantity,
                [
                    'reference_type' => 'adjustment',
                    'user_id' => $user->id,
                    'notes' => $request->string('reason', 'Manual adjustment'),
                ]
            );

            DB::commit();

            return $this->success(
                [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'previous_stock' => $product->stock_qty - $quantity,
                    'quantity_changed' => $quantity,
                    'current_stock' => $product->stock_qty,
                    'reason' => $request->string('reason', 'Manual adjustment'),
                ],
                'Stock adjusted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to adjust stock: '.$e->getMessage(), 500);
        }
    }

    /**
     * KEYBOARD-OPTIMIZED: Fast barcode/SKU lookup endpoint.
     *
     * Designed for keyboard-driven POS screens. Returns minimal payload (~300 bytes).
     * Used when cashier scans barcode to quickly add item to cart.
     *
     * @return JsonResponse with minimal fields: {id, sku, name, price, stock}
     * @example GET /api/v1/products/barcode/8001234567890
     */
    public function searchByBarcode(Request $request): JsonResponse
    {
        try {
            $barcode = $request->string('barcode');

            if (strlen($barcode) < 2) {
                return $this->error('Barcode must be at least 2 characters', 422);
            }

            $user = $request->user();

            // Fast query: exact match first for performance
            $product = Product::where('is_active', true);

            // Filter by branch if user is restricted
            if ($user && $user->branch_id) {
                $product->where('branch_id', $user->branch_id);
            }

            $product = $product->where('sku', $barcode)->first();

            // If exact match not found, try partial match (slower)
            if (! $product) {
                $product = Product::where('is_active', true);

                if ($user && $user->branch_id) {
                    $product->where('branch_id', $user->branch_id);
                }

                $product = $product->where('sku', 'like', "%{$barcode}%")->first();
            }

            if (! $product) {
                return $this->error('Product not found', 404);
            }

            // Minimal response payload for keyboard-driven screens
            return $this->success(
                [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'price' => $product->price,
                    'stock' => $product->stock_qty,
                    'available' => $product->stock_qty > 0,
                ],
                'Product found'
            );
        } catch (\Exception $e) {
            return $this->error('Barcode search failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * KEYBOARD-OPTIMIZED: Quick category/name search for order entry.
     *
     * Designed for fast keyboard-driven product lookup.
     * Returns up to 10 matching products with minimal fields.
     * Used for quick autocomplete on POS screen.
     *
     * @return JsonResponse with products array: {id, sku, name, price, stock}
     * @example GET /api/v1/products/quick-search?q=cola (returns ~500 bytes max)
     */
    public function quickSearch(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $search = $request->string('q');

            if (strlen($search) < 1) {
                return $this->success([], 'No search term provided');
            }

            $query = Product::where('is_active', true);

            // Filter by branch if user is restricted
            if ($user && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            $products = $query->where(function ($q) use ($search) {
                // Prioritize SKU matches (most likely barcode scan)
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
                ->select('id', 'sku', 'name', 'price', 'stock_qty')
                ->limit(10)
                ->get();

            // Lightweight response
            $formatted = $products->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'price' => $p->price,
                    'stock' => $p->stock_qty,
                ];
            });

            return $this->success($formatted, 'Products found');
        } catch (\Exception $e) {
            return $this->error('Quick search failed: '.$e->getMessage(), 500);
        }
    }
}

