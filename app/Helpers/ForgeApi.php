<?php

namespace App\Helpers;

use App\Models\CustomerSubscription;
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
                dd($customerSubscription->env);
                $customerSubscription->save();
            }
        }
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
