<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandingSyncLog extends Model
{
    protected $fillable = [
        'customer_subscription_id',
        'slot',
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

    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }
}
