<?php

namespace App\Console\Commands\SiteDeployment;

use App\Jobs\SendDeploymentScriptJob;
use App\Jobs\SendDeploymentScriptToForge;
use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class SendAllSitesDeployment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-all-sites-deployment {type-id}';

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
            SendDeploymentScriptJob::dispatch($customerSubscription)->delay($delay);
            $delay = $delay->addSeconds(10); // Increment delay by 1 minute for each job
        }
    }
}
