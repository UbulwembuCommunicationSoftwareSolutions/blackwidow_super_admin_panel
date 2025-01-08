<?php

namespace App\Jobs\SiteDeployment;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\CMSService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSystemConfigJob implements ShouldQueue
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
        $customer = Customer::find($this->customerId);
        $cmsService = new CMSService();
        $console = CustomerSubscription::where('subscription_type_id', 1)->where('customer_id', $customer->id)->first();
        if($console){
            $cmsService->setConsoleSystemConfigs($console);
        }
    }
}
