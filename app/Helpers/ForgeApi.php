<?php

namespace App\Helpers;

use App\Jobs\GetSitesForServerJob;
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

    public function horizonCreator($customerSubscription){
        $data = [
            'command' => 'php /home/forge/'.$customerSubscription->domain.'/artisan horizon'
        ];
        $this->forge->
        $this->forge->createDaemon($customerSubscription->server_id, $data);
    }

    public function sendDeploymentScript(CustomerSubscription $customerSubscription){
        $this->forge->updateSiteDeploymentScript($customerSubscription->server_id, $customerSubscription->forge_site_id,$customerSubscription->deploymentScript()->first()->script);
    }

    public function sendGitRepository($customerSubscription){
        $this->forge->installGitRepositoryOnSite(
            $customerSubscription->server_id,
            $customerSubscription->forge_site_id,
            [
                'provider' => 'github',
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'branch' => $customerSubscription->subscriptionType->branch,
            ]
        );
    }

    public function syncForge(){
        $servers = ForgeServer::get();
        foreach($servers as $server) {
            echo "Syncing Server: ".$server->name." with ID of : ".$server->forge_server_id." \n";
            GetSitesForServerJob::dispatch($server->forge_server_id);
        }
    }

    public function getSitesForServer($serverId){
        $sites = $this->getSites($serverId);
        foreach ($sites as $site) {
            $customerSubscription = CustomerSubscription::where('url', 'like', '%' . 'https://'.$site->name . '%')->first();
            if ($customerSubscription) {
                echo $customerSubscription->url.' '.$site->name."\n";
                $customerSubscription->forge_site_id = $site->id;
                $customerSubscription->save();
            } else {
                echo "No Subscription Found for " . $site->name . "\n";
                $customerSubscription = CustomerSubscription::updateOrCreate([
                    'url' => 'https://'.$site->name,
                    'subscription_type_id' => null,
                    'server_id' => $serverId,
                    'forge_site_id' => $site->id
                ]);
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
                \Log::info(json_encode($site));
            }
            return $sites;
        }catch (\Exception $e){
           // echo $e->getMessage();
        }
    }

    public function deploySite($server_id, $site_id){
        $this->forge->deploySite($server_id, $site_id);
    }

    public function letsEncryptCertificate(CustomerSubscription $customerSubscription){
        $domain = str_replace('http://','',$customerSubscription->url);
        $domain = str_replace('https://','',$domain);
        $this->forge->obtainLetsEncryptCertificate($customerSubscription->server_id, $customerSubscription->forge_site_id, [
            'domains' => [$domain],
            'wildcard' => false
        ]);
    }

    public function createSite($server_id, CustomerSubscription $customerSubscription){
        $this->addMissingEnv($customerSubscription);
        $template = null;
        if (in_array($customerSubscription->subscription_type_id, [1, 2, 9, 10])) {
            $database = $customerSubscription->database_name;
        } else {
            $database = null;
        }
        if($database){
            $payload = [
                'domain' => $customerSubscription->domain,
                'project_type' => $customerSubscription->subscriptionType->project_type,
                'directory' => $customerSubscription->subscriptionType->public_dir,
                'php_version' => 'php83',
                'nginx_template' => $customerSubscription->subscriptionType->nginx_template_id,
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'repository_provider' => 'github',
                'repository_branch' => $customerSubscription->subscriptionType->branch,
                'database' => $customerSubscription->database_name,
//            'env' => $this->collectEnv($customerSubscription)
            ];
        }else{
            $payload = [
                'domain' => $customerSubscription->domain,
                'project_type' => $customerSubscription->subscriptionType->project_type,
                'directory' => $customerSubscription->subscriptionType->public_dir,
                'php_version' => 'php83',
                'nginx_template' => $customerSubscription->subscriptionType->nginx_template_id,
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'repository_provider' => 'github',
                'repository_branch' => $customerSubscription->subscriptionType->branch,
//            'env' => $this->collectEnv($customerSubscription)
            ];
        }

        \Log::info(json_encode($payload));
        $this->forge->createSite($server_id,$payload);
        $this->syncForge();
    }



    public function addMissingEnv(CustomerSubscription $customerSubscription){
        if($customerSubscription->customer){
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

            $cmsUrl = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','VUE_APP_API_BASE_URL')
                ->first();

            if($cmsUrl){
                $caseManagement = CustomerSubscription::where('customer_id',$customerSubscription->customer_id)->where('subscription_type_id', 1)->first();
                $cmsUrl->value = $caseManagement->url;
                $cmsUrl->save();
            }

            $cmsUrl = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','CMS_URL')
                ->first();

            if($cmsUrl){
                $caseManagement = CustomerSubscription::where('customer_id',$customerSubscription->customer_id)->where('subscription_type_id', 1)->first();
                $cmsUrl->value = $caseManagement->url;
                $cmsUrl->save();
            }

            $appName = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','APP_NAME')
                ->first();
            if($appName){
                $appName->value = $customerSubscription->app_name;
                $appName->save();
            }

            $appName = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','VUE_APP_NAME')
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

            $secureToken = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','SECURE_TOKEN')
                ->first();
            if($secureToken){
                $secureToken->value = $customerSubscription->customer->token;
                $secureToken->save();
            }

            $minioBucket = EnvVariables::where('customer_subscription_id',$customerSubscription->id)
                ->where('key','MINIO_BUCKET')
                ->first();
            if($minioBucket){
                $minioBucket->value = $customerSubscription->database_name;
                $minioBucket->save();
            }
        }
    }


    public function sendEnv(CustomerSubscription $customerSubscription){

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
