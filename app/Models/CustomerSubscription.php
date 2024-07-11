<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerSubscription extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'app_url',
        'customer_id',
        'console_login_logo',
        'console_menu_logo',
        'console_background_logo',
        'app_install_logo',
        'app_background_logo',
        'subscription_type_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }
}
