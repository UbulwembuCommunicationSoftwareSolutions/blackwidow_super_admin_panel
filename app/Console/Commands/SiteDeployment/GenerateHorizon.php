<?php

namespace App\Console\Commands\SiteDeployment;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class GenerateHorizon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-horizon {customer-subscription-id}';

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
        $customerSubscription = CustomerSubscription::find($this->argument('customer-subscription-id'));
        $forgeApi = new ForgeApi();
        $forgeApi->horizonCreator($customerSubscription);
    }
}
