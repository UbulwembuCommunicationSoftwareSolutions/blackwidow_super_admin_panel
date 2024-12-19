<?php

namespace App\Console\Commands;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class SendSiteGitRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-site-git-repository {customer-subscription-id}';

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
        $subscription = CustomerSubscription::find($this->argument('customer-subscription-id'));
        $forge = new ForgeApi();
        $forge->sendGitRepository($subscription);
    }
}
