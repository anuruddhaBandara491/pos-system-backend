<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\CompleteOrderRequest;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    /**
     * Create new order (pending state).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            $validated = $request->validated();

            // Determine branch - use user's branch if assigned, otherwise use provided branch_id
            $branchId = $user->branch_id ?? $validated['branch_id'];

            // Verify user can access this branch
            if ($user->branch_id && $user->branch_id !== $branchId) {
                return $this->error('Unauthorized: Cannot create order for this branch', 403);
            }

            $branch = Branch::findOrFail($branchId);

            // Generate unique order number
            $orderNumber = Order::generateOrderNumber($branch);

            $order = Order::create([
                'branch_id' => $branchId,
                'cashier_id' => $user->id,
                'order_number' => $orderNumber,
                'discount' => $validated['discount'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]);

            DB::commit();

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'branch_id' => $order->branch_id,
                    'cashier_id' => $order->cashier_id,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'status' => $order->status,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at,
                ],
                'Order created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to create order: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get order details with items.
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            $order->load(['items.product', 'branch', 'cashier']);

            $items = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => [
                        'id' => $item->product->id,
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                    ],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ];
            });

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'branch' => [
                        'id' => $order->branch->id,
                        'name' => $order->branch->name,
                        'code' => $order->branch->code,
                    ],
                    'cashier' => [
                        'id' => $order->cashier->id,
                        'name' => $order->cashier->name,
                        'email' => $order->cashier->email,
                    ],
                    'items' => $items,
                    'item_count' => count($items),
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'status' => $order->status,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ],
                'Order retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve order: '.$e->getMessage(), 500);
        }
    }

    /**
     * List orders for authenticated user's branch or all orders for admin.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Order::query();

            // Restrict to user's branch if assigned
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            } elseif ($request->has('branch_id')) {
                // Allow filtering by branch for admins
                $query->where('branch_id', $request->integer('branch_id'));
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->string('status'));
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->date('from_date'));
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->date('to_date'));
            }

            // Sorting
            $sortBy = $request->string('sort_by', 'created_at');
            $sortOrder = $request->string('sort_order', 'desc');
            $allowedSortFields = ['order_number', 'total', 'created_at', 'status'];
            if (! in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            $query->orderBy($sortBy, $sortOrder);

            $orders = $query->with(['branch', 'cashier'])->paginate($request->integer('per_page', 15));

            $data = $orders->items();
            $formattedData = array_map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'branch' => [
                        'id' => $order->branch->id,
                        'name' => $order->branch->name,
                        'code' => $order->branch->code,
                    ],
                    'cashier' => [
                        'id' => $order->cashier->id,
                        'name' => $order->cashier->name,
                    ],
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                ];
            }, $data);

            return $this->success(
                [
                    'data' => $formattedData,
                    'pagination' => [
                        'total' => $orders->total(),
                        'per_page' => $orders->perPage(),
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                    ],
                ],
                'Orders retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve orders: '.$e->getMessage(), 500);
        }
    }

    /**
     * Add item to pending order.
     */
    public function addItem(StoreOrderItemRequest $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Verify order is pending
            if ($order->status !== 'pending') {
                return $this->error('Cannot add items to a '.$order->status.' order', 422);
            }

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            $validated = $request->validated();
            $product = Product::findOrFail($validated['product_id']);

            // Verify product is in same branch
            if ($product->branch_id !== $order->branch_id) {
                return $this->error('Product not available in this branch', 422);
            }

            // Check if product already in order
            $existingItem = $order->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                // Update quantity
                $existingItem->quantity += $validated['quantity'];
                $existingItem->calculateLineTotal();
            } else {
                // Create new item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'unit_price' => $product->price,
                    'line_total' => round($validated['quantity'] * $product->price, 2),
                ]);
            }

            // Recalculate order totals
            $order->calculateTotals();

            DB::commit();

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'item_count' => $order->items()->count(),
                ],
                'Item added successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to add item: '.$e->getMessage(), 500);
        }
    }

    /**
     * Remove item from pending order.
     */
    public function removeItem(Order $order, OrderItem $item): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Verify order is pending
            if ($order->status !== 'pending') {
                return $this->error('Cannot modify a '.$order->status.' order', 422);
            }

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Verify item belongs to order
            if ($item->order_id !== $order->id) {
                return $this->error('Item does not belong to this order', 422);
            }

            $item->delete();

            // Recalculate order totals
            $order->calculateTotals();

            DB::commit();

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'item_count' => $order->items()->count(),
                ],
                'Item removed successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to remove item: '.$e->getMessage(), 500);
        }
    }

    /**
     * Complete order (move from pending to completed).
     */
    public function complete(CompleteOrderRequest $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Verify order is pending
            if ($order->status !== 'pending') {
                return $this->error('Cannot complete a '.$order->status.' order', 422);
            }

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Verify order has items
            if ($order->items()->count() === 0) {
                return $this->error('Cannot complete order with no items', 422);
            }

            // Calculate final totals with tax rate
            $taxRate = $request->input('tax_rate', 0.1); // default 10% tax
            $order->calculateTotals($taxRate);

            // Reduce stock for each item in the order
            foreach ($order->items as $orderItem) {
                $product = $orderItem->product;
                $previousStock = $product->stock_qty;
                $quantitySold = $orderItem->quantity;

                // Update product stock
                $product->decrement('stock_qty', $quantitySold);

                // Record stock movement for sale
                StockMovement::record(
                    $order->branch_id,
                    $product->id,
                    'sale',
                    -$quantitySold,
                    [
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'user_id' => $user->id,
                        'notes' => "Order {$order->order_number}: {$product->sku}",
                    ]
                );
            }

            // Update status to completed
            $order->update(['status' => 'completed']);

            DB::commit();

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total' => $order->total,
                    'status' => $order->status,
                    'completed_at' => $order->updated_at,
                ],
                'Order completed successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to complete order: '.$e->getMessage(), 500);
        }
    }

    /**
     * Cancel order.
     */
    public function cancel(Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Only allow cancelling pending or completed orders
            if ($order->status === 'cancelled' || $order->status === 'refunded') {
                return $this->error('Cannot cancel a '.$order->status.' order', 422);
            }

            // If order was completed, restore stock and record reversal movements
            if ($order->status === 'completed') {
                foreach ($order->items as $orderItem) {
                    $product = $orderItem->product;
                    $quantitySold = $orderItem->quantity;

                    // Restore product stock
                    $product->increment('stock_qty', $quantitySold);

                    // Record stock movement for return
                    StockMovement::record(
                        $order->branch_id,
                        $product->id,
                        'return',
                        $quantitySold,
                        [
                            'reference_type' => 'order_cancel',
                            'reference_id' => $order->id,
                            'user_id' => $user->id,
                            'notes' => "Order {$order->order_number} cancelled: returned {$quantitySold} units",
                        ]
                    );
                }
            }

            $order->update(['status' => 'cancelled']);

            DB::commit();

            return $this->success(
                [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'cancelled_at' => $order->updated_at,
                ],
                'Order cancelled successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to cancel order: '.$e->getMessage(), 500);
        }
    }

    /**
     * KEYBOARD-OPTIMIZED: Streamlined quick-add item endpoint.
     *
     * Designed for high-frequency keyboard-driven item addition.
     * Minimal request/response payload. Fast path for adding products to cart.
     *
     * Request body: {product_id, quantity}
     * Response: ~200 bytes with current order totals
     *
     * @example POST /api/v1/orders/{id}/add-item {"product_id": 5, "quantity": 2}
     */
    public function quickAddItem(Request $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Verify order is pending
            if ($order->status !== 'pending') {
                return $this->error('Order is '.$order->status, 422);
            }

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Forbidden', 403);
            }

            // Minimal validation
            $productId = $request->integer('product_id');
            $quantity = $request->integer('quantity', 1);

            if ($quantity < 1) {
                return $this->error('Invalid quantity', 422);
            }

            $product = Product::findOrFail($productId);

            // Verify product is in same branch
            if ($product->branch_id !== $order->branch_id) {
                return $this->error('Product not available', 422);
            }

            // Check if product already in order
            $existingItem = $order->items()->where('product_id', $product->id)->first();

            if ($existingItem) {
                $existingItem->quantity += $quantity;
                $existingItem->calculateLineTotal();
            } else {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'line_total' => round($quantity * $product->price, 2),
                ]);
            }

            // Recalculate totals
            $order->calculateTotals();

            DB::commit();

            // Minimal response for keyboard screen
            return $this->success([
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'total' => $order->total,
                'items' => $order->items()->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * KEYBOARD-OPTIMIZED: Minimal order summary endpoint.
     *
     * Returns only essential data for keyboard-driven POS screen.
     * Payload: ~300 bytes with current totals and item count.
     *
     * @example GET /api/v1/orders/{id}/summary
     */
    public function getSummary(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Forbidden', 403);
            }

            return $this->success([
                'id' => $order->id,
                'number' => $order->order_number,
                'items' => $order->items()->count(),
                'subtotal' => $order->subtotal,
                'tax' => $order->tax,
                'discount' => $order->discount,
                'total' => $order->total,
                'paid' => $order->paid_amount,
                'balance' => $order->remaining_balance,
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * KEYBOARD-OPTIMIZED: Quick payment endpoint.
     *
     * Streamlined payment recording for keyboard-driven POS.
     * Single endpoint for both cash/card with minimal validation.
     *
     * Request: {method, amount} - Reference/notes optional
     * Response: ~150 bytes with new balance and order status
     *
     * @example POST /api/v1/orders/{id}/quick-pay {"method": "cash", "amount": 150}
     */
    public function quickPay(Request $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Forbidden', 403);
            }

            // Minimal validation
            $method = $request->string('method', 'cash');
            $amount = $request->float('amount', 0);

            if ($amount <= 0) {
                return $this->error('Invalid amount', 422);
            }

            if ($order->status === 'cancelled' || $order->status === 'refunded') {
                return $this->error('Cannot pay '.$order->status.' order', 422);
            }

            if ($amount > $order->remaining_balance) {
                return $this->error('Amount exceeds balance', 422);
            }

            // Record payment
            Payment::create([
                'order_id' => $order->id,
                'method' => in_array($method, ['cash', 'card', 'check', 'mobile', 'other']) ? $method : 'cash',
                'amount' => round($amount, 2),
                'status' => 'completed',
            ]);

            // Update order
            $order->paid_amount += $amount;
            $order->remaining_balance = round($order->total - $order->paid_amount, 2);

            if ($order->remaining_balance <= 0) {
                $order->status = 'completed';
                $order->remaining_balance = 0;
            }

            $order->save();

            DB::commit();

            // Ultra-minimal response for fast keyboard processing
            return $this->success([
                'paid' => $order->paid_amount,
                'balance' => $order->remaining_balance,
                'status' => $order->status,
                'complete' => $order->status === 'completed',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error($e->getMessage(), 500);
        }
    }
}

