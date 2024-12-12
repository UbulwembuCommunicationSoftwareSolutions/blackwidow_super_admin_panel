<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
class CustomerUser extends Model
{
    use HasApiTokens;

    public $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'email_address',
        'password',
    ];
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = \Hash::make($value);
    }
}
