<?php

namespace App\Console\Commands\SiteDeployment;

use Illuminate\Console\Command;

class SendEnvToAllConsoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-env-to-all-consoles';

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
        $subscriptions = \App\Models\CustomerSubscription::get();
        foreach($subscriptions as $subscription){
            $job = \App\Jobs\SendEnvToForge::dispatch($subscription->id);
        }
    }
}
