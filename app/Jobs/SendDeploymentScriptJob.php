<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDeploymentScriptJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */

    public $customerSubscription;
    public function __construct($customerSubscription)
    {
        $this->customerSubscription = $customerSubscription;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customerSubscription = $this->customerSubscription;
        $forgeApi = new ForgeApi();
        $script = DeploymentScript::where('customer_subscription_id',$customerSubscription->id)->first();
        $script->delete();
        $deploymentTemplate = DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->first();
        $siteDeployment = str_replace('#WEBSITE_URL#',$customerSubscription->domain,$deploymentTemplate->script);
        $script = DeploymentScript::updateOrCreate([
            'customer_subscription_id' => $customerSubscription->id
        ],[
            'script' => $siteDeployment
        ]);
        $script->save();
    }
}
