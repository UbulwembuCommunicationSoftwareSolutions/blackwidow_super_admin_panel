<?php

namespace App\Console\Commands\Generators;

use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class GenerateRequiredEnvsForSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-required-envs-for-sites';

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
        $subscriptions = CustomerSubscription::where('subscription_type_id', 3)->get();
        foreach ($subscriptions as $customerSubscription) {
            $forgeApi = new \App\Helpers\ForgeApi();
            $forgeApi->addMissingEnv($customerSubscription);
            $forgeApi->sendEnv($customerSubscription);
            $forgeApi->deploySite($customerSubscription->server_id,$customerSubscription->forge_site_id);
        }
    }
}
