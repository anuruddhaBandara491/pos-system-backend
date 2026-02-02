<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends BaseController
{
    /**
     * Get stock movements for a product with filters.
     *
     * Query params:
     * - type: 'sale', 'adjustment', 'return', 'damage', 'inventory_count'
     * - days: number of days back (default 30)
     * - limit: max results (default 50, max 500)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = request()->user();

            $request->validate([
                'type' => 'nullable|string|in:sale,adjustment,return,damage,inventory_count',
                'days' => 'nullable|integer|min:1|max:365',
                'limit' => 'nullable|integer|min:1|max:500',
            ]);

            $query = StockMovement::query();

            // Filter by branch if user is restricted
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            // Apply filters
            if ($request->filled('type')) {
                $query->byType($request->string('type'));
            }

            if ($request->filled('days')) {
                $query->recent($request->integer('days'));
            } else {
                $query->recent(30); // default 30 days
            }

            $limit = $request->integer('limit', 50);

            $movements = $query->with(['product', 'branch', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'product_id' => $movement->product_id,
                        'product' => [
                            'id' => $movement->product?->id,
                            'sku' => $movement->product?->sku,
                            'name' => $movement->product?->name,
                        ],
                        'type' => $movement->type,
                        'type_label' => $movement->getTypeLabel(),
                        'quantity' => $movement->quantity,
                        'balance_qty' => $movement->balance_qty,
                        'reference_type' => $movement->reference_type,
                        'reference_id' => $movement->reference_id,
                        'user_id' => $movement->user_id,
                        'user_name' => $movement->user?->name,
                        'notes' => $movement->notes,
                        'created_at' => $movement->created_at,
                    ];
                });

            return $this->success($movements, 'Stock movements retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve stock movements: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get stock movement history for a specific product.
     */
    public function productHistory(Product $product, Request $request): JsonResponse
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $product->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this product', 403);
            }

            $request->validate([
                'days' => 'nullable|integer|min:1|max:365',
                'limit' => 'nullable|integer|min:1|max:500',
            ]);

            $query = $product->stockMovements();

            if ($request->filled('days')) {
                $query->where('created_at', '>=', now()->subDays($request->integer('days')));
            } else {
                $query->where('created_at', '>=', now()->subDays(30)); // default 30 days
            }

            $limit = $request->integer('limit', 50);

            $movements = $query->with(['user'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'type' => $movement->type,
                        'type_label' => $movement->getTypeLabel(),
                        'quantity' => $movement->quantity,
                        'balance_qty' => $movement->balance_qty,
                        'reference_type' => $movement->reference_type,
                        'reference_id' => $movement->reference_id,
                        'user_name' => $movement->user?->name,
                        'notes' => $movement->notes,
                        'created_at' => $movement->created_at,
                    ];
                });

            return $this->success(
                [
                    'product' => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'current_stock' => $product->stock_qty,
                    ],
                    'movements' => $movements,
                ],
                'Product stock history retrieved'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product history: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get stock summary for all products in a branch.
     * Shows total sales, adjustments, returns, damage for a period.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $user = request()->user();

            $request->validate([
                'days' => 'nullable|integer|min:1|max:365',
            ]);

            $branchId = $user->branch_id ?? $request->integer('branch_id');
            $days = $request->integer('days', 30);

            $startDate = now()->subDays($days);

            // Get all movements in the period
            $movements = StockMovement::where('branch_id', $branchId)
                ->where('created_at', '>=', $startDate)
                ->with('product')
                ->get();

            // Group by type and calculate totals
            $summary = [
                'period_days' => $days,
                'start_date' => $startDate->toDateString(),
                'end_date' => now()->toDateString(),
                'by_type' => [
                    'sale' => [
                        'count' => 0,
                        'quantity' => 0,
                        'movements' => [],
                    ],
                    'adjustment' => [
                        'count' => 0,
                        'quantity' => 0,
                        'movements' => [],
                    ],
                    'return' => [
                        'count' => 0,
                        'quantity' => 0,
                        'movements' => [],
                    ],
                    'damage' => [
                        'count' => 0,
                        'quantity' => 0,
                        'movements' => [],
                    ],
                    'inventory_count' => [
                        'count' => 0,
                        'quantity' => 0,
                        'movements' => [],
                    ],
                ],
                'by_product' => [],
            ];

            foreach ($movements as $movement) {
                $type = $movement->type;

                // Update type summary
                $summary['by_type'][$type]['count']++;
                $summary['by_type'][$type]['quantity'] += $movement->quantity;

                // Update product summary
                $productId = $movement->product_id;
                if (!isset($summary['by_product'][$productId])) {
                    $summary['by_product'][$productId] = [
                        'product' => [
                            'id' => $movement->product?->id,
                            'sku' => $movement->product?->sku,
                            'name' => $movement->product?->name,
                        ],
                        'total_quantity' => 0,
                        'by_type' => [
                            'sale' => 0,
                            'adjustment' => 0,
                            'return' => 0,
                            'damage' => 0,
                        ],
                    ];
                }

                $summary['by_product'][$productId]['total_quantity'] += $movement->quantity;
                if (isset($summary['by_product'][$productId]['by_type'][$type])) {
                    $summary['by_product'][$productId]['by_type'][$type] += $movement->quantity;
                }
            }

            // Convert to indexed array
            $summary['by_product'] = array_values($summary['by_product']);

            return $this->success($summary, 'Stock movement summary retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve summary: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get movement detail by ID.
     */
    public function show(StockMovement $movement): JsonResponse
    {
        try {
            $user = request()->user();

            // Check branch access
            if ($user->branch_id && $movement->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this movement', 403);
            }

            return $this->success(
                [
                    'id' => $movement->id,
                    'product' => [
                        'id' => $movement->product?->id,
                        'sku' => $movement->product?->sku,
                        'name' => $movement->product?->name,
                    ],
                    'branch' => [
                        'id' => $movement->branch?->id,
                        'name' => $movement->branch?->name,
                    ],
                    'type' => $movement->type,
                    'type_label' => $movement->getTypeLabel(),
                    'quantity' => $movement->quantity,
                    'balance_qty' => $movement->balance_qty,
                    'reference_type' => $movement->reference_type,
                    'reference_id' => $movement->reference_id,
                    'user' => [
                        'id' => $movement->user?->id,
                        'name' => $movement->user?->name,
                        'email' => $movement->user?->email,
                    ],
                    'notes' => $movement->notes,
                    'created_at' => $movement->created_at,
                    'updated_at' => $movement->updated_at,
                ],
                'Stock movement retrieved'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve movement: '.$e->getMessage(), 500);
        }
    }
}
