<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'branch_id',
        'category_id',
        'sku',
        'name',
        'description',
        'price',
        'cost',
        'stock_qty',
        'reorder_level',
        'category',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock_qty' => 'integer',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the branch this product belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the category this product belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get stock movements for this product.
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get profit margin percentage.
     */
    public function getProfitMarginAttribute()
    {
        if ($this->cost == 0) {
            return 0;
        }

        return (($this->price - $this->cost) / $this->cost) * 100;
    }

    /**
     * Check if stock is low.
     */
    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->reorder_level;
    }
}
