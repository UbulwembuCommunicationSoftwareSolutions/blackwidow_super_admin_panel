<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use App\Models\DeploymentTemplate;
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

    public static function setSitesDeploymentScripts(){
        $customerSubscriptions = CustomerSubscription::get();
        foreach($customerSubscriptions as $customerSubscription){
            if(DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->exists()){
                $forgeApi = new ForgeApi();
                $deploymentTemplate = DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->first();
                $baseUrl = str_replace('https://','',$customerSubscription->url);
                $baseUrl = str_replace('http://','',$baseUrl);
                $deploymentString = str_replace('#WEBSITE_URL#',$baseUrl,$deploymentTemplate->script);
                if($customerSubscription->server_id && $customerSubscription->forge_site_id){
                    try{
                        $forgeApi->forge->updateSiteDeploymentScript($customerSubscription->serverId, $customerSubscription->forge_site_id, $deploymentString);
                        echo "Success ". $customerSubscription->url.','.$customerSubscription->server_id.','.$customerSubscription->forge_site_id.' '.$e->getMessage()."\n";

                    }catch (\Exception $e){
                        echo $customerSubscription->url.','.$customerSubscription->server_id.','.$customerSubscription->forge_site_id.' '.$e->getMessage()."\n";
                    }
                }
            }
        }
    }
}
