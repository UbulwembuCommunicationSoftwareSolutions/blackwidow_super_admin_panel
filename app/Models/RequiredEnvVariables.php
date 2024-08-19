<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequiredEnvVariables extends Model
{

    public $fillable = [
        'subscription_type_id',
        'key',
        'value',
        'created_at',
        'updated_at',
    ];
    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }
}
