<?php

namespace App\Helpers;

use App\Jobs\SendEnvToForge;
use App\Jobs\TriggerForgeDeployment;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use App\Models\EnvVariables;
use App\Models\ForgeServer;
use App\Models\RequiredEnvVariables;
use Dotenv\Dotenv;
use Laravel\Forge\Forge;

class ForgeApi
{

    public $forge;

    public $servers;

    public $sites;
    public function __construct()
    {
        $this->forge = new Forge(env('FORGE_API_KEY'));
    }

    public function sendCommand($customerSubscriptionId,$command){
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $commands_array["command"] = $command;
        $this->forge->executeSiteCommand($customerSubscription->server_id, $customerSubscription->forge_site_id, $commands_array);
    }

    public function sendDeploymentScript($customerSubscriptionId,$script){
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $this->forge->updateSiteDeploymentScript($customerSubscription->server_id, $customerSubscription->forge_site_id,$script);
    }

    public function syncForge(){
        $this->getServers();
        foreach($this->servers as $server) {
            ForgeServer::updateOrCreate([
                'forge_server_id' => $server->id
            ],[
                'name' => $server->name,
                'ip_address' => $server->ipAddress
            ]);
            $sites = $this->getSites($server->id);
            foreach ($sites as $site) {
                $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $site->name . '%')->first();
                if ($customerSubscription) {
                } else {
                    echo "No Subscription Found for " . $site->name . "\n";
                    $customerSubscription = CustomerSubscription::create([
                        'url' => $site->name,
                        'subscription_type_id' => null,
                        'server_id' => $server->id,
                        'forge_site_id' => $site->id
                    ]);
                }
            }
        }
        $customerSubscriptions = CustomerSubscription::whereNotNull('forge_site_id')
            ->whereNull('deployment_script_sent_at')
            ->get();
        foreach($customerSubscriptions as $customerSubscription){
           $siteDeployment = DeploymentScript::where('customer_subscription_id', $customerSubscription->id)->first();
           if($siteDeployment){
                $this->sendDeploymentScript($customerSubscription->id, $siteDeployment->script);
                $customerSubscription->deployment_script_sent_at = now();
                $customerSubscription->save();
           }else{
               $deploymentTemplate = DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->first();
               $baseUrl = str_replace('https://','',$customerSubscription->url);
               $baseUrl = str_replace('http://','',$baseUrl);
               $siteDeployment = str_replace('#WEBSITE_URL#',$baseUrl,$deploymentTemplate->script);
               $deploymentScript = DeploymentScript::updateOrCreate([
                   'customer_subscription_id' => $customerSubscription->id
               ],[
                   'script' => $siteDeployment
               ]);
               $deploymentScript->save();
               $this->sendDeploymentScript($customerSubscription->id, $siteDeployment->script);
               $customerSubscription->deployment_script_sent_at = now();
               $customerSubscription->save();
           }
        }
    }

    public function deployAllConsoles(){
        $customerSubscriptions = CustomerSubscription::where('subscription_type_id', 1)->get();
        foreach($customerSubscriptions as $customerSubscription){
            if($customerSubscription->server_id == null || $customerSubscription->forge_site_id == null){
                \Log::error("Server ID or Site ID not found for Subscription ID: ".$customerSubscription->id);
            }else{
                TriggerForgeDeployment::dispatch($customerSubscription->server_id, $customerSubscription->forge_site_id);
            }
        }
    }
    public function parseEnvContent($content) {
        $lines = explode("\n", $content);
        $env = [];

        foreach ($lines as $line) {
            if (empty($line) || strpos(trim($line), '#') === 0) {
                continue;
            }

            list($key, $value) = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }
            $env[$key] = $value;
        }

        return $env;
    }
    public function getServers(){
        $this->servers = $this->forge->servers();
        return $this->servers;
    }

    public function getSites($serverId){
        $sites = [];
        try{
            foreach($this->forge->sites($serverId) as $site){
                $sites[] = $site;
            }
            return $sites;
        }catch (\Exception $e){
           // echo $e->getMessage();
        }
    }

    public function deploySite($server_id, $site_id){
        $this->forge->deploySite($server_id, $site_id);
    }

    public function createSite($server_id, CustomerSubscription $customerSubscription){
        $this->addMissingEnv($customerSubscription);
        $domain = str_replace('http://','',$customerSubscription->url);
        $domain = str_replace('https://','',$domain);
        $payload = [
            'domain' => $domain,
            'project_type' => 'php',
            'directory' => '/public',
            'php_version' => 'php83',
            'database' => $customerSubscription->database_name,
//            'env' => $this->collectEnv($customerSubscription)
        ];
        \Log::info(json_encode($payload));
        $this->forge->createSite($server_id,$payload);
        $this->syncForge();
    }



    public function addMissingEnv(CustomerSubscription $customerSubscription){
        $addedEnv = EnvVariables::where('customer_subscription_id', $customerSubscription->id)->pluck('key');
        $missing = RequiredEnvVariables::where('subscription_type_id', $customerSubscription->subscription_type_id)
            ->whereNotIn('key', $addedEnv)
            ->get();

        foreach ($missing as $env) {
            EnvVariables::updateOrCreate([
                'key' => $env->key,
                'customer_subscription_id' => $customerSubscription->id
            ],[
                'value' => $env->value
            ]);
        }
        $database = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
            ->where('key','DB_DATABASE')
            ->first();
        if($database){
            $database->value = $customerSubscription->database_name;
            $database->save();
        }

        $appName = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
            ->where('key','APP_NAME')
            ->first();
        if($appName){
            $appName->value = $customerSubscription->app_name;
            $appName->save();
        }

        $appUrl = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
            ->where('key','APP_URL')
            ->first();
        if($appUrl){
            $appUrl->value = $customerSubscription->url;
            $appUrl->save();
        }

        $elasticSearch = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
            ->where('key','ELASTICSEARCH_INDEX')
            ->first();
        if($elasticSearch){
            $elasticSearch->value = $customerSubscription->database_name;
            $elasticSearch->save();
        }

        $minioBucket = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
            ->where('key','MINIO_BUCKET')
            ->first();
        if($minioBucket){
            $minioBucket->value = $customerSubscription->database_name;
            $minioBucket->save();
        }


    }


    public function sendEnv($customerSubscriptionId){

        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $env = $this->collectEnv($customerSubscription);
        $this->forge->updateSiteEnvironmentFile($customerSubscription->server_id, $customerSubscription->forge_site_id, $env);
    }

    public function collectEnv($customerSubscription){

        $envFileStr = '';
        $envVariables = EnvVariables::where('customer_subscription_id', $customerSubscription->id)->orderBy('key')->get();
        foreach($envVariables as $env){
            $envFileStr.= $env->key."=".$env->value."\r";
        }
        //echo $envFileStr;
       return $envFileStr;

    }
}
