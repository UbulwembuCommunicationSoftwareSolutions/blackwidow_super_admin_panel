<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvVariables extends Model
{

    public $fillable = [
        'key',
        'value',
        'customer_subscription_id',
        'created_at',
        'updated_at',
    ];
    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }
}
