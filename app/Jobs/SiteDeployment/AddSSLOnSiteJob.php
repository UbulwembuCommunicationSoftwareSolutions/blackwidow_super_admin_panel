<?php

namespace App\Jobs\SiteDeployment;

use App\Models\CustomerSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AddSSLOnSiteJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */

    public $customerSubscriptionId;
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
        $forgeApi->letsEncryptCertificate($customerSubscription);
    }
}
