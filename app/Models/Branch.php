<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the users assigned to this branch.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the products in this branch.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the orders in this branch.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
