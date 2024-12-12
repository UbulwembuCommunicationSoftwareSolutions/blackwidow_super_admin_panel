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
        'name',
        'email',
        'password',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'lat',
        'lng',
        'google_address',
    ];
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = \Hash::make($value);
    }
}
