<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerSubscription extends Model
{
    protected $fillable = [
        'url',
        'domain',
        'subscription_type_id',
        'server_id',
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
        'database_name',
        'app_name',
        'env',
        'site_created_at',
        'github_sent_at',
        'env_sent_at',
        'deployment_script_sent_at',
        'ssl_deployed_at',
        'deployed_at',
    ];

    public $appends = ['null_variable_count'];

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public function deploymentScript(){
        return $this->hasMany(DeploymentScript::class);
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
                        echo $e->key . " found in " . $subscription->id . "\n";
                        $found = true;
                        break;
                    }
                }
                if(!$found){
                    echo $env->key . " not found in " . $subscription->id . "\n";
                    $newEnv = new EnvVariables();
                    $newEnv->key = $env->key;
                    $newEnv->value = $env->value;
                    $newEnv->customer_subscription_id = $subscription->id;
                    $newEnv->save();
                }
            }
        }
    }

    public function envVariables()
    {
        return $this->hasMany(EnvVariables::class);
    }


    public function getNullVariableCountAttribute(){
        return $this->envVariables()->whereNull('value')->count();
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
