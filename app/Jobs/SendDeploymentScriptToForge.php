<?php

namespace App\Jobs;

use App\Models\CustomerSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeploymentScriptToForge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $customerSubscriptionId;

    public $script;
    /**
     * Create a new job instance.
     */
    public function __construct($customerSubscriptionId)
    {
        $this->customerSubscriptionId = $customerSubscriptionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customerSubscription = CustomerSubscription::find($this->customerSubscriptionId);
        $forgeApi = new \App\Helpers\ForgeApi();
        $forgeApi->sendDeploymentScript($customerSubscription);
    }
}
