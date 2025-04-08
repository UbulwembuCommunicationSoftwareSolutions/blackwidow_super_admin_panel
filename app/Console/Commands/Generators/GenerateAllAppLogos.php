<?php

namespace App\Console\Commands\Generators;

use App\Helpers\ForgeApi;
use App\Jobs\SendDeploymentScriptToForge;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use App\Services\CustomerSubscriptionService;
use Illuminate\Console\Command;

class GenerateAllAppLogos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-app-logos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(){
        $subscriptions = CustomerSubscription::where('subscription_type_id', 3)->get();
        foreach($subscriptions as $subscription){
            CustomerSubscriptionService::generatePWALogos($subscription->id);
        }
    }
}
