<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductPermission extends Model
{
    protected $fillable = [
        'product',
        'name',
        'group_name',
        'sub_group_name',
    ];

    public function customerUsers(): BelongsToMany
    {
        return $this->belongsToMany(CustomerUser::class, 'customer_user_product_permissions');
    }
}
