<?php

namespace App\Models;

use Str;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes, HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            // Your logic here
            // For example, call a method on the model
            if (strlen($model->token) > 0) {
                return;
            } else {
                $model->token = Str::uuid();
                $model->uuid = Str::uuid();
                $model->save();
            }
        });

        static::updating(function ($model) {
            $updated = false;
            if($model->isDirty('level_one_in_use')){
                $updated = true;
            }
            if($model->isDirty('level_two_in_use')){
                $updated = true;
            }
            if($model->isDirty('level_three_in_use')){
                $updated = true;
            }
            if($model->isDirty('level_one_description')){
                $updated = true;
            }
            if($model->isDirty('level_two_description')){
                $updated = true;
            }
            if($model->isDirty('level_three_description')){
                $updated = true;
            }
            if($model->isDirty('level_four_description')){
                $updated = true;
            }
            if($model->isDirty('level_five_description')){
                $updated = true;
            }
            if($model->isDirty('task_description')){
                $updated = true;
            }
            if($model->isDirty('docket_description')){
                $updated = true;
            }
            if($updated){
                SendSystemConfigJob::dispatch($model->id);
            }
        });
    }
    protected $fillable = [
        'company_name',
        'token',
        'max_users',
        'docket_description',
        'task_description',
        'level_one_description',
        'level_one_in_use',
        'level_two_description',
        'level_two_in_use',
        'level_three_description',
        'level_three_in_use',
        'level_four_description',
        'level_five_description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_customers');
    }

    public function customerSubscriptions() : hasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function customerUsers() : hasMany
    {
        return $this->hasMany(CustomerUser::class);
    }
}
