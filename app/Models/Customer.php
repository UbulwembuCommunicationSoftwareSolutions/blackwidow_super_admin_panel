<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes, HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            // Your logic here
            // For example, call a method on the model
            if (strlen($model->token) > 0) {
                return;
            } else {
                $model->token = \Str::uuid();
                $model->save();
            }
        });
    }
    protected $fillable = [
        'company_name',
        'token',
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
