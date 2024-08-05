<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSubscription extends Model
{
    protected $fillable = [
        'url',
        'subscription_type_id',
        'customer_id',
        'logo_1',
        'logo_2',
        'logo_3',
        'logo_4',
        'logo_5',
        'created_at',
        'updated_at',
    ];

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
