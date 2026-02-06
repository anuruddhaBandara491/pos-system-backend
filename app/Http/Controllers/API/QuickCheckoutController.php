<?php

namespace App\Http\Controllers\API;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class QuickCheckoutController extends BaseController
{
    /**
     * Complete a sale in a single atomic operation.
     * Best for fast POS checkout - combines order creation, items, payment, and completion.
     *
     * POST /api/v1/quick-checkout
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function completeSale(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0', // Optional price override
            'payment' => 'required|array',
            'payment.method' => 'required|string|in:cash,card,mobile_money,bank_transfer',
            'payment.amount' => 'required|numeric|min:0',
            'payment.reference' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }
        DB::beginTransaction();

        try {
            $user = auth('api')->user();
            $branchId = $request->input('branch_id', $user->branch_id);

            // Validate stock availability FIRST (before creating order)
            $items = $request->input('items');
            $stockErrors = [];

            foreach ($items as $index => $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    $stockErrors["items.{$index}.product_id"] = ["Product not found"];
                    continue;
                }

                if (!$product->is_active) {
                    $stockErrors["items.{$index}.product_id"] = ["Product is not active"];
                }

                if ($product->stock_qty < $item['quantity']) {
                    $stockErrors["items.{$index}.quantity"] = [
                        "Insufficient stock. Available: {$product->stock_qty}, Requested: {$item['quantity']}"
                    ];
                }
            }

            if (!empty($stockErrors)) {
                DB::rollBack();
                return $this->error('Stock validation failed', 422, $stockErrors);
            }

            // 1. Create Order
            $order = Order::create([
                'branch_id' => $branchId,
                'cashier_id' => $user->id,
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . '-' . rand(1000, 9999),
                'status' => 'pending',
                'subtotal' => 0,
                'tax' => 0,
                'discount' => $request->input('discount', 0),
                'total' => 0,
                'notes' => $request->input('notes'),
            ]);

            // 2. Add Items & Calculate Totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $price = $item['price'] ?? $product->price; // Allow price override
                $quantity = $item['quantity'];
                $lineTotal = $price * $quantity;

                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;

                // Prepare stock reduction
                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'item' => $orderItem,
                ];
            }

            // 3. Calculate Tax & Total
            $taxRate = $request->input('tax_rate', 0);
            $discount = $request->input('discount', 0);
            $tax = ($subtotal - $discount) * $taxRate;
            $total = $subtotal + $tax - $discount;

            $order->update([
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);

            // 4. Record Payment
            $paymentData = $request->input('payment');
            $paymentAmount = $paymentData['amount'];

            if ($paymentAmount < $total) {
                DB::rollBack();
                return $this->error('Insufficient payment amount', 422, [
                    'required' => $total,
                    'received' => $paymentAmount,
                    'balance' => $total - $paymentAmount,
                ]);
            }

            $payment = $order->payments()->create([
                'method' => $paymentData['method'],
                'amount' => $paymentAmount,
                'reference' => $paymentData['reference'] ?? null,
                'status' => 'completed',
            ]);

            // 5. Reduce Stock & Complete Order
            foreach ($orderItems as $item) {
                $product = $item['product'];
                $product->decrement('stock_qty', $item['quantity']);

                // Record stock movement
                $product->stockMovements()->create([
                    'type' => 'sale',
                    'quantity' => -$item['quantity'],
                    'branch_id' => $branchId,
                    'balance_qty' => $product->stock_qty,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => "Sale from order {$order->order_number}",
                ]);
            }
            $change = max(0, $paymentAmount - $total);
            $order->update([
                'status' => 'completed',
                'total_paid' => $paymentAmount,
                'balance' => $change,
            ]);

            DB::commit();

            // Return minimal response for fast POS
            return $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $total,
                'paid' => $paymentAmount,
                'change' => $change,
                'items_count' => count($items),
                'status' => 'completed',
            ], 'Sale completed successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error(
                'Failed to complete sale: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Batch create multiple sales (for offline sync).
     *
     * POST /api/v1/quick-checkout/batch
     */
    public function batchCompleteSales(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sales' => 'required|array|min:1|max:50', // Max 50 sales per batch
            'sales.*.items' => 'required|array|min:1',
            'sales.*.payment' => 'required|array',
            // ... same validation as completeSale
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($request->input('sales') as $index => $saleData) {
            try {
                $fakeRequest = new Request($saleData);
                $response = $this->completeSale($fakeRequest);

                if ($response->getStatusCode() === 201) {
                    $results['successful'][] = [
                        'index' => $index,
                        'data' => json_decode($response->getContent(), true)
                    ];
                } else {
                    $results['failed'][] = [
                        'index' => $index,
                        'error' => json_decode($response->getContent(), true)
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $this->success([
            'total' => count($request->input('sales')),
            'successful' => count($results['successful']),
            'failed' => count($results['failed']),
            'results' => $results,
        ], 'Batch processing completed');
    }
}
