<?php

namespace App\Jobs;

use App\Services\CMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartUserSyncJob implements ShouldQueue
{
    use Queueable;

    public $customerId;
    /**
     * Create a new job instance.
     */
    public function __construct($customerId)
    {
        $this->customerId = $customerId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        CMSService::syncUsers($this->customerId);
    }
}
