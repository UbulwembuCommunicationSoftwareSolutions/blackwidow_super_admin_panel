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

    public function syncForge(){
        $this->getServers();
        foreach($this->servers as $server){
            $this->getSites($server->id);
        }

        foreach($this->sites as $site){
            $customerSubscription = CustomerSubscription::where('url','like','%'.$site->name.'%')->first();
            if($customerSubscription){
                $customerSubscription->forge_site_id = $site->id;
                echo $customerSubscription->url."\n";
                $customerSubscription->env = $this->forge->siteEnvironmentFile($site->serverId, $site->id);
                $env = $this->parseEnvContent($customerSubscription->env);
                foreach($env as $key=>$value){
                    $envVar = EnvVariables::firstOrCreate(
                        [ 'key'=>$key,'customer_subscription_id'=>$customerSubscription->id],[
                            'value'=>$value,
                            'customer_subscription_id'=>$customerSubscription->id
                    ]);
                }
                $customerSubscription->save();
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
}
