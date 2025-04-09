<?php

namespace App\Jobs\SiteDeployment;

use App\Models\CustomerSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeploySite implements ShouldQueue
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
        $customerSubscription = CustomerSubscription::find($this->customerSubscriptionId);
        $forgeApi = new \App\Helpers\ForgeApi();
        $customerSubscription->deployed_at = now();
        $customerSubscription->deployed_version = $customerSubscription->subscriptionType->master_version;
        $customerSubscription->save();
        $forgeApi->deploySite($customerSubscription->server_id,$customerSubscription->forge_site_id);
    }
}
