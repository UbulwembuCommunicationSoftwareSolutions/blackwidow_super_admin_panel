<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentTemplate extends Model
{

    public $fillable = [
        'script',
        'native_script',
        'subscription_type_id',
    ];
    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    /**
     * True when this template has a Forge zero-downtime (release based) script, so sites of its
     * subscription type can be created as native zero-downtime sites.
     */
    public function supportsZeroDowntime(): bool
    {
        return filled($this->native_script);
    }
}
