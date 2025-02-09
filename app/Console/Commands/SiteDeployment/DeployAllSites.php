<?php

namespace App\Console\Commands\SiteDeployment;

use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class DeployAllSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deploy-site-by-type {type-id}';

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
        $customerSubscriptions = CustomerSubscription::where('subscription_type_id',$this->argument('type-id'))->get();
        $delay = now(); // Start with the current time

        foreach ($customerSubscriptions as $customerSubscription) {
            \App\Jobs\SiteDeployment\DeploySite::dispatch($customerSubscription->id)->delay($delay);
            $delay = $delay->addMinute(); // Increment delay by 1 minute for each job
        }
    }
}
