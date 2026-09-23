<?php

namespace App\Models;

use App\Jobs\PushCustomerUserFieldToTenantsJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerUserField extends Model
{
    use HasFactory;
    use SoftDeletes;

    public bool $skipSync = false;

    protected $fillable = [
        'customer_id',
        'name',
        'label',
        'type',
        'rules',
        'options',
        'sort_order',
        'active',
        'skip_sync',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
            'skip_sync' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $model): void {
            if ($model->skipSync || $model->skip_sync) {
                return;
            }

            PushCustomerUserFieldToTenantsJob::dispatch($model->id, 'upsert');
        });

        static::deleted(function (self $model): void {
            if ($model->skipSync || $model->skip_sync) {
                return;
            }

            PushCustomerUserFieldToTenantsJob::dispatch($model->id, 'archive');
        });

        static::restored(function (self $model): void {
            if ($model->skipSync || $model->skip_sync) {
                return;
            }

            PushCustomerUserFieldToTenantsJob::dispatch($model->id, 'restore');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomerUserFieldValue::class);
    }
}
