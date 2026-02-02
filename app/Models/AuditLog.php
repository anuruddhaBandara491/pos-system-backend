<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'method',
        'endpoint',
        'ip_address',
        'user_agent',
        'response_code',
        'changes',
        'details',
    ];

    protected $casts = [
        'changes' => 'json',
        'details' => 'json',
        'response_code' => 'integer',
    ];

    protected $dates = [
        'created_at',
    ];

    /**
     * Get the user associated with this audit log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an action.
     *
     * @param string $action
     * @param array $data
     * @return self
     */
    public static function logAction(
        string $action,
        array $data = []
    ): self {
        return self::create([
            'user_id' => auth('api')->user()->id(),
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'method' => $data['method'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'response_code' => $data['response_code'] ?? null,
            'changes' => $data['changes'] ?? null,
            'details' => $data['details'] ?? null,
        ]);
    }

    /**
     * Get logs for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get logs for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get logs for a specific entity.
     */
    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)
            ->where('entity_id', $id);
    }

    /**
     * Get logs within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get recent logs (last N).
     */
    public function scopeRecent($query, int $limit = 100)
    {
        return $query->latest()->limit($limit);
    }
}
