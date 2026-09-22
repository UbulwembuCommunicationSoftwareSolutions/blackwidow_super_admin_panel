<?php

namespace App\Models;

use App\Jobs\PushBrandingToTenantsJob;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscriptionBrandSlot extends Model
{
    use HasFactory;

    /**
     * When true, model hooks must not queue an outbound branding push (inbound apply).
     */
    public bool $skipSync = false;

    protected $fillable = [
        'customer_subscription_id',
        'slot',
        'customer_branding_media_id',
        'is_override',
        'cleared',
    ];

    protected function casts(): array
    {
        return [
            'is_override' => 'boolean',
            'cleared' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (CustomerSubscriptionBrandSlot $model): void {
            $model->queueBrandingPushIfNeeded();
        });

        static::updated(function (CustomerSubscriptionBrandSlot $model): void {
            $model->queueBrandingPushIfNeeded();
        });
    }

    private function queueBrandingPushIfNeeded(): void
    {
        if ($this->skipSync) {
            return;
        }

        $slot = (string) $this->slot;
        if (! in_array($slot, BrandingSyncPayload::SLOTS, true)) {
            return;
        }

        if (! $this->wasRecentlyCreated && ! $this->wasChanged([
            'customer_branding_media_id',
            'is_override',
            'cleared',
        ])) {
            return;
        }

        PushBrandingToTenantsJob::dispatch(
            subscriptionId: $this->customer_subscription_id,
            cmsSlots: [$slot],
        );
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CustomerBrandingMedia::class, 'customer_branding_media_id');
    }
}
