<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateSiteOnForge implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $customerSubscriptionId;
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
        $forgeApi = new \App\Helpers\ForgeApi();
        $customerSubscription = \App\Models\CustomerSubscription::find($this->customerSubscriptionId);

        $forgeApi->createSite($customerSubscription->server_id,$customerSubscription);
    }
}
