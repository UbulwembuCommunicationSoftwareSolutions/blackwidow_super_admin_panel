<?php

namespace App\Models;

use App\Services\SiteDeploymentJobName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscriptionDeploymentJob extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'customer_subscription_id',
        'batch_id',
        'position',
        'job_name',
        'parameters',
        'status',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    public function isSendForgeCommand(): bool
    {
        return $this->job_name === SiteDeploymentJobName::SEND_FORGE_COMMAND;
    }

    public function isSendSystemConfig(): bool
    {
        return $this->job_name === SiteDeploymentJobName::SEND_SYSTEM_CONFIG;
    }
}
