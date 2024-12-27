<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NginxTemplate extends Model
{
    public $fillable = ['name', 'server_id', 'template_id'];
}
