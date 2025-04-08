<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionType extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'github_repo',
        'project_type',
        'public_dir',
        'branch',
        'master_version'
    ];
}
