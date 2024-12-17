<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;

class ForgeService
{
    public static function getAllSiteEnvironments()
    {
        $subscriptions = CustomerSubscription::all();
        foreach($subscriptions as $subscription)
        {
           self::getSiteEnvironment($subscription);
        }

    }

    public static function getSiteEnvironment($subscription)
    {
        $forgeApi = new ForgeApi();
        $string_env = $forgeApi->forge->siteEnvironmentFile($subscription->serverId, $subscription->forge_site_id);
        $subscription->env = $string_env;
        $env = $forgeApi->parseEnvContent($string_env);
        foreach($env as $key=>$value){
            if($key!=='FORGE_API_KEY'){
                $envVar = EnvVariables::where('key', $key)
                    ->where('customer_subscription_id', $subscription->id)
                    ->first();
                if(!$envVar){
                    $envVar = new EnvVariables();
                    $envVar->key = $key;
                    $envVar->value = $value;
                    $envVar->customer_subscription_id = $subscription->id;
                    $envVar->save();
                }else{
                    $envVar->value = $value;
                    $envVar->save();
                }
            }
        }
        $subscription->save();
    }
}
