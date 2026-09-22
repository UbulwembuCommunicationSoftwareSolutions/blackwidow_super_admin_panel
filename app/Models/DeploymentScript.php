<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentScript extends Model
{
    protected $fillable = [
        'customer_subscription_id',
        'script',
        'rendered_release_id',
        'is_custom',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }

    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    public function renderedRelease(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTypeRelease::class, 'rendered_release_id');
    }
}
