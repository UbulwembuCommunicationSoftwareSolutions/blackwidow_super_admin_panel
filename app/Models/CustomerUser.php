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
        'console_access',
        'firearm_access',
        'responder_access',
        'reporter_access',
        'security_access',
        'driver_access',
        'survey_access',
        'time_and_attendance_access',
        'stock_access',
        'created_at',
        'cellphone'
    ];
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = \Hash::make($value);
    }
}
