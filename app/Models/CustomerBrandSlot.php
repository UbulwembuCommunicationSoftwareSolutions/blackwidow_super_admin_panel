<?php

namespace App\Models;

use App\Jobs\PushBrandingToTenantsJob;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBrandSlot extends Model
{
    use HasFactory;

    /**
     * When true, model hooks must not queue an outbound branding push (inbound apply).
     */
    public bool $skipSync = false;

    protected $fillable = [
        'customer_id',
        'slot',
        'customer_branding_media_id',
    ];

    protected static function booted(): void
    {
        static::updated(function (CustomerBrandSlot $model): void {
            if ($model->skipSync) {
                return;
            }

            if (! $model->wasChanged('customer_branding_media_id')) {
                return;
            }

            $slot = (string) $model->slot;
            if (! in_array($slot, BrandingSyncPayload::SLOTS, true)) {
                return;
            }

            PushBrandingToTenantsJob::dispatch(
                customerId: $model->customer_id,
                cmsSlots: [$slot],
            );
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CustomerBrandingMedia::class, 'customer_branding_media_id');
    }
}
