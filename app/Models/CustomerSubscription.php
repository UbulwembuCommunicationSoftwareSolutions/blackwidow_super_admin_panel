<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSubscription extends Model
{
    protected $fillable = [
        'url',
        'subscription_type_id',
        'customer_id',
        'logo_1',
        'logo_2',
        'logo_3',
        'env',
        'forge_site_id',
        'logo_4',
        'logo_5',
        'created_at',
        'updated_at',
    ];

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public static function createMissingEnv(){
        $subscriptions = CustomerSubscription::get();
        foreach($subscriptions as $subscription){
            $envs = $subscription->envVariables;
            $requiredEnv = RequiredEnvVariables::where('subscription_type_id', $subscription->subscription_type_id)->get();
            foreach($requiredEnv as $env){
                $found = false;
                foreach($envs as $e){
                    if($e->key == $env->key){
                        $found = true;
                        break;
                    }
                }
                if(!$found){
                    $newEnv = new EnvVariables();
                    $newEnv->key = $env->key;
                    $newEnv->value = $env->value;
                    $newEnv->customer_subscription_id = $id;
                    $newEnv->save();
                }
            }
        }
    }

    public function envVariables()
    {
        return $this->hasMany(EnvVariables::class);
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
