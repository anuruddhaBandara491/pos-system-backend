<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PaymentIdempotencyKey Model
 * Stores idempotency keys to prevent duplicate payment processing
 */
class PaymentIdempotencyKey extends Model
{
    protected $table = 'payment_idempotency_keys';

    protected $fillable = [
        'id',
        'idempotency_key',
        'payment_id',
        'status',
        'response',
        'expires_at',
    ];

    protected $casts = [
        'response' => 'array',
        'expires_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get the payment associated with this idempotency key
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    /**
     * Check if idempotency key is still valid (not expired)
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Generate a UUID for the key
     */
    public static function generateId(): string
    {
        return \Illuminate\Support\Str::uuid()->toString();
    }
}
