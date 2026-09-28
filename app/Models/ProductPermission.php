<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'product',
        'name',
        'guard_name',
        'group_name',
        'sub_group_name',
        'is_active',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function customerUsers(): BelongsToMany
    {
        return $this->belongsToMany(CustomerUser::class, 'customer_user_product_permissions');
    }

    public function subscriptionGrants(): BelongsToMany
    {
        return $this->belongsToMany(CustomerUser::class, 'customer_user_subscription_permissions')
            ->withPivot('customer_subscription_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProduct(Builder $query, string $product): Builder
    {
        return $query->where('product', $product);
    }
}
