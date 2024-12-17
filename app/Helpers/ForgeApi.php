<?php

namespace App\Helpers;

use App\Jobs\TriggerForgeDeployment;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\EnvVariables;
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

    public function sendDeploymentScript($customerSubscriptionId){
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $deploymentScript = DeploymentScript::where('customer_subscription_id', $customerSubscriptionId)->first();
        $this->forge->updateSiteDeploymentScript($customerSubscription->server_id, $customerSubscription->forge_site_id, $deploymentScript->script);
    }

    public function syncForge(){
        $this->getServers();
        foreach($this->servers as $server) {
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


    public function sendEnv($customerSubscriptionId){

        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $envFileStr = '';
        $envVariables = EnvVariables::where('customer_subscription_id', $customerSubscriptionId)->orderBy('key')->get();
        foreach($envVariables as $env){
            $envFileStr.= $env->key."=".$env->value."\r";
        }
        //echo $envFileStr;
        echo "Site Id: ". $customerSubscription->forge_site_id."\n";
        echo "Server Id: ". $customerSubscription->server_id."\n";

        $this->forge->updateSiteEnvironmentFile($customerSubscription->server_id, $customerSubscription->forge_site_id, $envFileStr);
    }
}
