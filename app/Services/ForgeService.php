<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Jobs\SendDeploymentScriptToForge;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use App\Models\EnvVariables;
use Illuminate\Support\Facades\Log;

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

    public static function getSiteEnvironment(CustomerSubscription $subscription): void
    {
        $forgeApi = new ForgeApi();
        $response = $forgeApi->forge->siteEnvironmentFile($subscription->server_id, $subscription->forge_site_id);

        $responseForLog = is_string($response)
            ? $response
            : json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        Log::info('Pull env from Forge: received API response', [
            'customer_subscription_id' => $subscription->id,
            'server_id' => $subscription->server_id,
            'forge_site_id' => $subscription->forge_site_id,
            'response_json' => $responseForLog,
        ]);

        $string_env = self::resolveEnvFileContent($response);
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

    /**
     * Forge returns JSON (e.g. ['content' => '...']) from the site env API; normalize to a plain string.
     */
    private static function resolveEnvFileContent(mixed $response): string
    {
        if (is_array($response)) {
            return (string) ($response['content'] ?? '');
        }

        return is_string($response) ? $response : (string) $response;
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
                $deploymentScript = DeploymentScript::updateOrCreate([
                    'customer_subscription_id' => $customerSubscription->id
                ],[
                    'script' => $deploymentString
                ]);
                $deploymentScript->save();
                if($customerSubscription->server_id && $customerSubscription->forge_site_id){
                    SendDeploymentScriptToForge::dispatch($customerSubscription->id,$deploymentString);
                }
            }
        }
    }
}
