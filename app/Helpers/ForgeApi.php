<?php

namespace App\Helpers;

use App\Models\CustomerSubscription;
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

    public function syncForge(){
        $this->getServers();
        foreach($this->servers as $server){
            $this->getSites($server->id);
        }

        foreach($this->sites as $site){
            $customerSubscription = CustomerSubscription::where('url','like','%'.$site->name.'%')->first();
            if($customerSubscription){
                $customerSubscription->forge_site_id = $site->id;
                //echo $customerSubscription->url."\n";
                $string_env = $this->forge->siteEnvironmentFile($site->serverId, $site->id);
                $customerSubscription->server_id = $site->serverId;
                $customerSubscription->save();

                $string_deployment = $this->forge->siteDeploymentScript($site->serverId, $site->id);
                dd($string_deployment);
                $env = $this->parseEnvContent($string_env);
                try{
                }catch (\Exception $e) {
//                    echo $e->getMessage();
                }
                foreach($env as $key=>$value){
                    if($key!=='FORGE_API_KEY'){
                        $envVar = EnvVariables::where('key', $key)
                            ->where('customer_subscription_id', $customerSubscription->id)
                            ->first();
                        if(!$envVar){
                            $envVar = new EnvVariables();
                            $envVar->key = $key;
                            $envVar->value = $value;
                            $envVar->customer_subscription_id = $customerSubscription->id;
                            $envVar->save();
                        }else{
                            $envVar->value = $value;
                            $envVar->save();
                        }
                    }
                }
                $customerSubscription->save();
            }else{
                echo "No Subscription Found for ".$site->name."\n";
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
        try{
            foreach($this->forge->sites($serverId) as $site){
                $this->sites[] = $site;
            }
        }catch (\Exception $e){
           // echo $e->getMessage();
        }
    }

    public function deploySite($customerSubscriptionId){
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        $this->forge->deploySite($customerSubscription->server_id, $customerSubscription->forge_site_id);
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
