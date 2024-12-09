<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'company_name',
    ];

    public function customerSubscriptions() : hasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function customerUsers() : hasMany
    {
        return $this->hasMany(CustomerUser::class);
    }
}
