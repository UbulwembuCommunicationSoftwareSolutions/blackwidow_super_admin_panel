<?php

namespace App\Models;

use App\Jobs\SendSubscriptionEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Services\CMSService;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class CustomerUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

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

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            // Your logic here
            if($model->console_access){
                SendWelcomeEmailJob::dispatch($model);
            };
            if($model->firearm_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',1)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->responder_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',3)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->reporter_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',4)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->security_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',5)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->driver_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',6)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->survey_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',7)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->time_and_attendance_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',9)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
            if($model->stock_access){
                $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                    ->where('subscription_type_id',10)
                    ->first();
                if($subscription){
                  SendSubscriptionEmailJob::dispatch($model,$subscription);
                }
            }
        });

        // Handle 'updated' event
        static::updated(function ($model) {
            // Your logic here
            if ($model->wasChanged('console_access')) {
                if($model->console_access){
                    SendWelcomeEmailJob::dispatch($model);
                }else{
                    CMSService::suspendService($model);
                }
            }
            if ($model->wasChanged('firearm_access')) {
                if($model->firearm_access){
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id',1)
                        ->first();
                    if($subscription){
                        SendSubscriptionEmailJob::dispatch($model,$subscription);
                    }
                }

            }
            if ($model->wasChanged('responder_access')) {
                if($model->responder_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 3)
                        ->first();
                    if($subscription){
                        SendSubscriptionEmailJob::dispatch($model,$subscription);
                    }
                }
                // Example: If a specific field was updated, dispatch a job
            }
            if ($model->wasChanged('reporter_access')) {
                if($model->reporter_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 4)
                        ->first();
                    if($subscription){
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }
            if ($model->wasChanged('security_access')) {
                if($model->security_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 5)
                        ->first();
                    // Example: If a specific field was updated, dispatch a job
                    if($subscription) {
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }
            if ($model->wasChanged('driver_access')) {
                if($model->driver_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 6)
                        ->first();
                    if($subscription) {
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }
            if ($model->wasChanged('survey_access')) {
                if($model->survey_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 7)
                        ->first();
                    if($subscription) {
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }
            if ($model->wasChanged('time_and_attendance_access')) {
                if($model->time_and_attendance_access) {
                    // Example: If a specific field was updated, dispatch a job
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 9)
                        ->first();
                    if($subscription) {
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }
            if ($model->wasChanged('stock_access')) {
                if($model->stock_access) {
                    $subscription = CustomerSubscription::where('customer_id', $model->customer_id)
                        ->where('subscription_type_id', 10)
                        ->first();
                    // Example: If a specific field was updated, dispatch a job
                    if($subscription) {
                        SendSubscriptionEmailJob::dispatch($model, $subscription);
                    }
                }
            }

        });
    }
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = \Hash::make($value);
    }
}
