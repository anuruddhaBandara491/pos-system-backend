<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'branch_id',
        'cashier_id',
        'order_number',
        'subtotal',
        'tax',
        'discount',
        'total',
        'paid_amount',
        'remaining_balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    /**
     * Get the branch this order belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the cashier who created this order.
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Get the items in this order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payments for this order.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Calculate and update order totals.
     */
    public function calculateTotals(float $taxRate = 0.1): void
    {
        $this->subtotal = $this->items()->sum('line_total');
        $this->tax = round($this->subtotal * $taxRate, 2);
        $this->total = round($this->subtotal + $this->tax - $this->discount, 2);
        $this->remaining_balance = round($this->total - $this->paid_amount, 2);
        $this->save();
    }

    /**
     * Check if order is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->remaining_balance <= 0;
    }

    /**
     * Get total paid for order including all payment records.
     */
    public function getTotalPaid(): float
    {
        return $this->payments()
            ->where('status', '!=', 'failed')
            ->sum('amount');
    }

    /**
     * Generate unique order number for branch.
     */
    public static function generateOrderNumber(Branch $branch): string
    {
        $today = now()->format('Ymd');
        $count = static::where('branch_id', $branch->id)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('ORD-%s-%03d', $today, $count);
    }
}
