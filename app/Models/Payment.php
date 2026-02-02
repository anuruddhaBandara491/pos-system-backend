<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment Model
 * Represents a payment transaction for an order
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'order_id',
        'amount',
        'method',
        'status',
        'reference',
        'idempotency_key',
        'timestamp',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'timestamp' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get the order that this payment belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Generate a unique payment ID
     */
    public static function generateId(): string
    {
        return 'pay_' . bin2hex(random_bytes(12));
    }

    /**
     * Get total amount paid for an order
     */
    public static function getTotalPaidForOrder(string $orderId): float
    {
        return static::where('order_id', $orderId)
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get balance remaining for an order
     */
    public static function getBalanceForOrder(string $orderId): array
    {
        $order = Order::findOrFail($orderId);
        $totalPaid = static::getTotalPaidForOrder($orderId);
        $balanceRemaining = $order->total - $totalPaid;

        $status = 'unpaid';
        if ($balanceRemaining <= 0) {
            $status = 'paid';
        } elseif ($totalPaid > 0) {
            $status = 'partial';
        }

        return [
            'orderId' => $orderId,
            'totalAmount' => $order->total,
            'totalPaid' => $totalPaid,
            'balanceRemaining' => max($balanceRemaining, 0),
            'status' => $status,
        ];
    }
}
