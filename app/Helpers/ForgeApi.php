<?php

namespace App\Helpers;

use Laravel\Forge\Forge;

class ForgeApi
{

    public $forge;

    public $servers;

    public $sites;
    public function __construct()
    {
        $this->forge = new Forge(env('FORGE_API_KEY'));
        $this->getServers();
        foreach($this->servers as $server){
            $this->getSites($server->id);
        }
    }

    public function getServers(){
        $this->servers = $this->forge->servers();
        return $this->servers;
    }

    public function getSites($serverId){
        $this->sites = $this->forge->sites($serverId);
        return $this->sites;
    }
}
