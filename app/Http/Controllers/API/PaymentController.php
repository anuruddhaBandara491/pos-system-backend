<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\RecordPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentIdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends BaseController
{
    /**
     * Submit a payment for an order (idempotent endpoint)
     * POST /api/v1/payments/submit
     */
    public function submit(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'orderId' => 'required|string|exists:orders,id',
                'amount' => 'required|numeric|gt:0',
                'method' => 'required|in:cash,card,check',
                'reference' => 'nullable|string|max:100',
                'metadata' => 'nullable|array|max:1024',
            ]);

            $idempotencyKey = $request->header('Idempotency-Key');

            // Validate idempotency key exists
            if (!$idempotencyKey) {
                return $this->badRequest('Idempotency-Key header is required', [
                    'header' => 'Idempotency-Key'
                ]);
            }

            // Check for existing payment with this idempotency key
            $existingKey = PaymentIdempotencyKey::where('idempotency_key', $idempotencyKey)->first();

            if ($existingKey && $existingKey->isValid()) {
                if ($existingKey->status === 'processing') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Payment is currently being processed',
                        'code' => 'PAYMENT_PROCESSING',
                        'status' => 409
                    ], 409);
                }

                if ($existingKey->status === 'completed') {
                    // Return cached response
                    return response()->json(
                        json_decode($existingKey->response, true),
                        200
                    );
                }
            }

            // Start database transaction
            return DB::transaction(function () use ($validated, $idempotencyKey, $existingKey) {
                // Get order with lock for update
                $order = Order::lockForUpdate()
                    ->where('id', $validated['orderId'])
                    ->firstOrFail();

                // Calculate current balance
                $totalPaid = Payment::getTotalPaidForOrder($order->id);
                $balanceRemaining = $order->total - $totalPaid;

                // Validate payment amount
                if ($validated['amount'] <= 0) {
                    return $this->badRequest('Amount must be greater than zero', [
                        'amount' => 'Amount must be greater than zero'
                    ]);
                }

                // Check if order is already fully paid
                if ($balanceRemaining <= 0) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Order is already fully paid',
                        'code' => 'ORDER_ALREADY_PAID',
                        'orderId' => $order->id,
                        'balanceRemaining' => 0,
                        'status' => 409
                    ], 409);
                }

                // Check if payment amount exceeds balance
                if ($validated['amount'] > $balanceRemaining) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Payment amount exceeds remaining balance',
                        'code' => 'AMOUNT_EXCEEDS_BALANCE',
                        'requestedAmount' => (float)$validated['amount'],
                        'balanceRemaining' => (float)$balanceRemaining,
                        'status' => 400
                    ], 400);
                }

                // Create payment record
                $paymentId = Payment::generateId();
                $payment = Payment::create([
                    'id' => $paymentId,
                    'order_id' => $order->id,
                    'amount' => $validated['amount'],
                    'method' => $validated['method'],
                    'status' => 'completed',
                    'reference' => $validated['reference'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'timestamp' => now(),
                    'metadata' => $validated['metadata'] ?? null,
                ]);

                // Recalculate balance
                $newTotalPaid = Payment::getTotalPaidForOrder($order->id);
                $newBalanceRemaining = $order->total - $newTotalPaid;

                // Update order status if fully paid
                if ($newBalanceRemaining <= 0) {
                    $order->update(['status' => 'paid']);
                    $orderStatus = 'paid';
                } else {
                    $order->update(['status' => 'partial']);
                    $orderStatus = 'partial';
                }

                // Store idempotency key with response
                $responseData = [
                    'success' => true,
                    'payment' => [
                        'paymentId' => $payment->id,
                        'orderId' => $payment->order_id,
                        'amount' => (float)$payment->amount,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'reference' => $payment->reference,
                        'timestamp' => $payment->timestamp->toIso8601String(),
                        'balanceRemaining' => (float)$newBalanceRemaining,
                        'totalPaid' => (float)$newTotalPaid,
                        'orderStatus' => $orderStatus,
                    ]
                ];

                // Update or create idempotency key record
                PaymentIdempotencyKey::updateOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'id' => PaymentIdempotencyKey::generateId(),
                        'payment_id' => $paymentId,
                        'status' => 'completed',
                        'response' => json_encode($responseData),
                        'expires_at' => now()->addDay(),
                    ]
                );

                return $this->success($responseData['payment'], 'Payment processed successfully', 201);
            });

        } catch (ValidationException $e) {
            return $this->badRequest('Validation failed', $e->errors());
        } catch (\Exception $e) {
            Log::error('Payment submission error: ' . $e->getMessage());
            return $this->error('Internal server error', 500, [
                'message' => 'Payment processing failed'
            ]);
        }
    }

    /**
     * Record payment for order (backward compatible endpoint).
     */
    public function recordPayment(RecordPaymentRequest $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Verify order is not cancelled/refunded
            if ($order->status === 'cancelled' || $order->status === 'refunded') {
                return $this->error('Cannot process payment for a '.$order->status.' order', 422);
            }

            $validated = $request->validated();
            $paymentAmount = $validated['amount'];

            // Check if payment amount exceeds remaining balance
            if ($paymentAmount > $order->remaining_balance) {
                return $this->error(
                    'Payment amount exceeds remaining balance of '.$order->remaining_balance,
                    422
                );
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $validated['method'],
                'amount' => $paymentAmount,
                'reference' => $validated['reference'] ?? null,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Update order paid_amount and remaining_balance
            $order->paid_amount += $paymentAmount;
            $order->remaining_balance = round($order->total - $order->paid_amount, 2);

            // Mark order as completed if fully paid
            if ($order->remaining_balance <= 0) {
                $order->status = 'completed';
                $order->remaining_balance = 0; // Zero out any rounding errors
            }

            $order->save();

            DB::commit();

            return $this->success(
                [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'method' => $payment->method,
                    'amount_paid' => $payment->amount,
                    'previous_balance' => round($order->total - ($order->paid_amount - $paymentAmount), 2),
                    'current_balance' => $order->remaining_balance,
                    'total_paid' => $order->paid_amount,
                    'order_total' => $order->total,
                    'order_status' => $order->status,
                    'change' => abs($order->remaining_balance) > 0 ? 0 : abs($order->remaining_balance),
                ],
                'Payment recorded successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to record payment: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get payment balance for an order
     * GET /api/v1/payments/balance/{orderId}
     */
    public function getBalance(string $orderId): JsonResponse
    {
        try {
            // Verify order exists
            $order = Order::findOrFail($orderId);

            // Get balance information
            $totalPaid = Payment::getTotalPaidForOrder($orderId);
            $balanceRemaining = $order->total - $totalPaid;

            $status = 'unpaid';
            if ($balanceRemaining <= 0) {
                $status = 'paid';
            } elseif ($totalPaid > 0) {
                $status = 'partial';
            }

            // Get all payments for this order
            $payments = Payment::where('order_id', $orderId)
                ->where('status', 'completed')
                ->select('id', 'method', 'amount', 'timestamp', 'status')
                ->orderBy('timestamp', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'paymentId' => $payment->id,
                        'method' => $payment->method,
                        'amount' => (float)$payment->amount,
                        'timestamp' => $payment->timestamp ? $payment->timestamp->toIso8601String() : null,
                        'status' => $payment->status,
                    ];
                });

            $balanceData = [
                'orderId' => $orderId,
                'totalAmount' => (float)$order->total,
                'totalPaid' => (float)$totalPaid,
                'balanceRemaining' => (float)max($balanceRemaining, 0),
                'status' => $status,
                'payments' => $payments->toArray(),
            ];

            return $this->success($balanceData, 'Balance retrieved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
                'code' => 'ORDER_NOT_FOUND',
                'orderId' => $orderId,
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            Log::error('Balance retrieval error: ' . $e->getMessage());
            return $this->error('Failed to calculate balance', 500, [
                'code' => 'BALANCE_CALCULATION_ERROR'
            ]);
        }
    }

    /**
     * Get payment status confirmation
     * GET /api/v1/payments/{paymentId}/status
     */
    public function getStatus(string $paymentId): JsonResponse
    {
        try {
            // Get payment with order information
            $payment = Payment::with('order')->findOrFail($paymentId);

            // Recalculate balance from backend
            $totalPaid = Payment::getTotalPaidForOrder($payment->order_id);
            $balanceRemaining = $payment->order->total - $totalPaid;

            $orderStatus = 'unpaid';
            if ($balanceRemaining <= 0) {
                $orderStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $orderStatus = 'partial';
            }

            $statusData = [
                'paymentId' => $payment->id,
                'orderId' => $payment->order_id,
                'amount' => (float)$payment->amount,
                'method' => $payment->method,
                'paymentStatus' => $payment->status,
                'timestamp' => $payment->timestamp ? $payment->timestamp->toIso8601String() : null,
                'confirmedAt' => now()->toIso8601String(),
                'balanceRemaining' => (float)$balanceRemaining,
                'totalPaid' => (float)$totalPaid,
                'orderStatus' => $orderStatus,
            ];

            return $this->success($statusData, 'Payment status confirmed');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Payment not found',
                'code' => 'PAYMENT_NOT_FOUND',
                'paymentId' => $paymentId,
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            Log::error('Payment status confirmation error: ' . $e->getMessage());
            return $this->error('Failed to confirm payment status', 500, [
                'code' => 'STATUS_CONFIRMATION_ERROR'
            ]);
        }
    }

    /**
     * Get payment history for an order
     * GET /api/v1/payments/history/{orderId}
     */
    public function getHistory(string $orderId, Request $request): JsonResponse
    {
        try {
            // Validate order exists
            $order = Order::findOrFail($orderId);

            // Get pagination parameters
            $limit = min($request->get('limit', 100), 500);
            $offset = $request->get('offset', 0);

            // Get payment history
            $payments = Payment::where('order_id', $orderId)
                ->where('status', 'completed')
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($payment) {
                    return [
                        'paymentId' => $payment->id,
                        'method' => $payment->method,
                        'amount' => (float)$payment->amount,
                        'timestamp' => $payment->timestamp ? $payment->timestamp->toIso8601String() : null,
                        'status' => $payment->status,
                        'reference' => $payment->reference,
                    ];
                });

            // Calculate totals
            $totalPaid = Payment::getTotalPaidForOrder($orderId);

            $historyData = [
                'orderId' => $orderId,
                'totalPayments' => $payments->count(),
                'totalAmount' => (float)$order->total,
                'totalPaid' => (float)$totalPaid,
                'payments' => $payments->toArray(),
            ];

            return $this->success($historyData, 'Payment history retrieved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
                'code' => 'ORDER_NOT_FOUND',
                'orderId' => $orderId,
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            Log::error('Payment history retrieval error: ' . $e->getMessage());
            return $this->error('Failed to retrieve payment history', 500, [
                'code' => 'HISTORY_RETRIEVAL_ERROR'
            ]);
        }
    }

    /**
     * Get payment history for order (backward compatible endpoint).
     */
    public function getPaymentHistory(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            $payments = $order->payments()->orderBy('created_at', 'desc')->get();

            $formattedPayments = $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                    'notes' => $payment->notes,
                    'created_at' => $payment->created_at,
                ];
            });

            return $this->success(
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_total' => $order->total,
                    'total_paid' => $order->total_paid,
                    'balance' => $order->balance,
                    'payment_count' => $payments->count(),
                    'payments' => $formattedPayments,
                ],
                'Payment history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve payment history: '.$e->getMessage(), 500);
        }
    }

    /**
     * Refund payment.
     */
    public function refundPayment(RecordPaymentRequest $request, Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            // Check authorization - only managers/admins can refund
            if (! $user->hasAnyRole(['manager', 'admin'])) {
                return $this->error('Unauthorized: Only managers and admins can refund payments', 403);
            }

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            // Verify order is not already refunded
            if ($order->status === 'refunded') {
                return $this->error('Order is already refunded', 422);
            }

            // Verify order has payments
            if ($order->total_paid <= 0) {
                return $this->error('No payments to refund', 422);
            }

            $validated = $request->validated();
            $refundAmount = $validated['amount'];

            // Check if refund amount exceeds paid amount
            if ($refundAmount > $order->total_paid) {
                return $this->error(
                    'Refund amount exceeds paid amount of '.$order->total_paid,
                    422
                );
            }

            // Create refund payment record (negative amount)
            $payment = Payment::create([
                'order_id' => $order->id,
                'method' => $validated['method'],
                'amount' => -$refundAmount,
                'reference' => $validated['reference'] ?? null,
                'status' => 'refunded',
                'notes' => ($validated['notes'] ?? '').' [REFUND]',
            ]);

            // Update order paid_amount and remaining_balance
            $order->total_paid -= $refundAmount;
            $order->balance = round($order->total - $order->total_paid, 2);
            $order->status = 'refunded';
            $order->save();

            DB::commit();

            return $this->success(
                [
                    'refund_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'refund_amount' => abs($payment->amount),
                    'previous_paid' => $order->total_paid + $refundAmount,
                    'current_paid' => $order->total_paid,
                    'order_total' => $order->total,
                    'order_status' => $order->status,
                ],
                'Refund processed successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->error('Failed to process refund: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get order payment summary.
     */
    public function getPaymentSummary(Order $order): JsonResponse
    {
        try {
            $user = request()->user();

            // Check if user can access this order
            if ($user->branch_id && $order->branch_id !== $user->branch_id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            $paymentsByMethod = $order->payments()
                ->where('status', '!=', 'failed')
                ->selectRaw('method, count(*) as count, sum(amount) as total')
                ->groupBy('method')
                ->get();

            return $this->success(
                [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_total' => $order->total,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'total_paid' => $order->total_paid,
                    'balance' => $order->balance,
                    'is_fully_paid' => $order->remaining_balance <= 0,
                    'payment_methods' => $paymentsByMethod,
                    'order_status' => $order->status,
                ],
                'Payment summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve payment summary: '.$e->getMessage(), 500);
        }
    }
}
