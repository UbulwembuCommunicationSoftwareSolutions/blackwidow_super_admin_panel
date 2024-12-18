<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForgeServer extends Model
{
    protected $table = 'my_forge_servers';
    protected $fillable = [
        'forge_server_id',
        'id',
        'name',
        'ip_address',
    ];
}
