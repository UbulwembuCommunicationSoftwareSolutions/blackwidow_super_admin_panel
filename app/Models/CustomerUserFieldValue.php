<?php

namespace App\Models;

use App\Jobs\PushCustomerUserFieldValuesToTenantsJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUserFieldValue extends Model
{
    use HasFactory;

    public bool $skipSync = false;

    protected $fillable = [
        'customer_user_id',
        'customer_user_field_id',
        'value',
        'skip_sync',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skip_sync' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $dispatch = function (self $model): void {
            if ($model->skipSync || $model->skip_sync) {
                return;
            }

            PushCustomerUserFieldValuesToTenantsJob::dispatch($model->customer_user_id);
        };

        static::saved($dispatch);
        static::deleted($dispatch);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class, 'customer_user_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomerUserField::class, 'customer_user_field_id');
    }
}
