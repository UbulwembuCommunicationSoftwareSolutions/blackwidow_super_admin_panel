<?php

namespace App\Console\Commands\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Jobs\SendDeploymentScriptToForge;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

class SendSiteDeploymentScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-site-deployment-script {customer-subscription-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle():void
    {
        $customerSubscription = CustomerSubscription::findOrFail($this->argument('customer-subscription-id'));
        $deploymentScript = DeploymentScript::where('customer_subscription_id', $customerSubscription->id)->first();
        $deploymentScript->delete();
        if(DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->exists()){
            $forgeApi = new ForgeApi();
            $deploymentTemplate = DeploymentTemplate::where('subscription_type_id',$customerSubscription->subscription_type_id)->first();
            $baseUrl = str_replace('https://','',$customerSubscription->url);
            $baseUrl = str_replace('http://','',$baseUrl);
            $deploymentString = str_replace('#WEBSITE_URL#',$baseUrl,$deploymentTemplate->script);
            $deploymentScript = DeploymentScript::updateOrCreate([
                'customer_subscription_id' => $customerSubscription->id
            ],[
                'script' => $deploymentString
            ]);
            $deploymentScript->save();
            if($customerSubscription->server_id && $customerSubscription->forge_site_id){
                SendDeploymentScriptToForge::dispatch($customerSubscription->id,$deploymentString);
            }
        }
    }
}
