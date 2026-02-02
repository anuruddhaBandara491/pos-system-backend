<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'balance_qty',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_qty' => 'decimal:2',
    ];

    /**
     * Get the product this stock movement is for.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the branch this stock movement belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who initiated this movement.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a stock movement for a product.
     *
     * @param  int  $branchId
     * @param  int  $productId
     * @param  string  $type - 'sale', 'adjustment', 'return', 'damage', 'inventory_count'
     * @param  int  $quantity - positive or negative
     * @param  array  $meta - ['reference_type', 'reference_id', 'user_id', 'notes']
     * @return StockMovement
     */
    public static function record(
        int $branchId,
        int $productId,
        string $type,
        int $quantity,
        array $meta = []
    ): StockMovement {
        // Fetch product for current balance
        $product = Product::findOrFail($productId);

        // Calculate balance after movement
        $balanceAfter = $product->stock_qty + $quantity;

        // Prevent negative stock (unless explicitly allowed for adjustments)
        if ($balanceAfter < 0 && $type !== 'damage') {
            throw new \Exception("Insufficient stock: attempting to reduce by {$quantity}, current: {$product->stock_qty}");
        }

        // Create movement record
        $movement = new self([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'balance_qty' => $balanceAfter,
        ]);

        $movement->save();

        return $movement;
    }

    /**
     * Get movement type label for display.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'sale' => 'Sale',
            'adjustment' => 'Adjustment',
            'return' => 'Return',
            'damage' => 'Damage',
            'inventory_count' => 'Inventory Count',
            default => ucfirst($this->type),
        };
    }

    /**
     * Scope: Get movements for a specific product.
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Get movements for a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Get movements by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get recent movements (last N days).
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get stock report for a branch and date range.
     */
    public static function getStockReport(int $branchId, string $startDate = null, string $endDate = null)
    {
        $query = self::where('branch_id', $branchId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->with(['product', 'user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('product_id');
    }
};
