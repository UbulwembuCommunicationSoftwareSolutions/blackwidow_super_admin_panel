<?php

namespace App\Jobs\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddDeploymentScriptOnForgeJob implements ShouldQueue
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
        $forgeApi = new ForgeApi();
        $script = DeploymentScript::where('customer_subscription_id',$customerSubscription->id)->first();
        if(!$script){
            $deploymentTemplate = DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->first();
            $baseUrl = str_replace('https://','',$customerSubscription->url);
            $baseUrl = str_replace('http://','',$baseUrl);
            $siteDeployment = str_replace('#WEBSITE_URL#',$baseUrl,$deploymentTemplate->script);
            $script = DeploymentScript::updateOrCreate([
                'customer_subscription_id' => $customerSubscription->id
            ],[
                'script' => $siteDeployment
            ]);
            $script->save();
        }
        if($script){
            $forgeApi->sendDeploymentScript($customerSubscription,$script->script);
        }
    }
}
