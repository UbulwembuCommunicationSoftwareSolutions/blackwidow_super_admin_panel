<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSyncLog extends Model
{
    protected $fillable = [
        'customer_user_id',
        'direction',
        'status',
        'error_message',
        'sync_data',
        'synced_at',
    ];

    protected $casts = [
        'sync_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class);
    }

    // Scopes
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeConflicts($query)
    {
        return $query->where('status', 'conflict');
    }

    public function scopeByDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('synced_at', '>=', now()->subDays($days));
    }
}
