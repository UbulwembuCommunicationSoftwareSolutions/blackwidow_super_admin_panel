<?php

namespace App\Jobs;

use App\Services\CMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Queueable;

    public $customerUser;
    /**
     * Create a new job instance.
     */
    public function __construct($customerUser)
    {
        $this->customerUser = $customerUser;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cms = new CMSService();
        $cms->sendWelcomeEmail($this->customerUser);
    }
}
