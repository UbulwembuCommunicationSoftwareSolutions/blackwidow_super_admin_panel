<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentScript extends Model
{

    protected $fillable = [
        'customer_subscription_id',
        'script',
        'created_at',
        'updated_at',
    ];
    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }
}
