<?php

namespace App\Console\Commands\Generators;

use App\Helpers\ForgeApi;
use App\Jobs\SendDeploymentScriptToForge;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

class GenerateDeploymentScriptForSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-deployment-script-for-sites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customerSubscriptions = \App\Models\CustomerSubscription::where('subscription_type_id', 3)->get();
        foreach($customerSubscriptions as $customerSubscription){
            $deploymentScript = DeploymentScript::where('customer_subscription_id', $customerSubscription->id)->first();
            if($deploymentScript){
                $deploymentScript->delete();
            }
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
}
