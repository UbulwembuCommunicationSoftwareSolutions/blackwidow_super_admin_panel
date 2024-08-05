<?php

namespace App\Helpers;

use Laravel\Forge\Forge;

class ForgeApi
{

    public $forge;

    public function __construct()
    {
        $this->forge = new Forge(env('FORGE_API_KEY'));
        dd($this->forge->servers());
    }
}
