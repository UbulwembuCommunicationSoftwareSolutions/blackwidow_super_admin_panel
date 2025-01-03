<?php

namespace App\Console\Commands\SiteApi;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Services\CMSService;
use Illuminate\Console\Command;

class SendCompanyDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-company-descriptions {customer}';

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
        $customer = Customer::find($this->argument('customer'));
        $cmsService = new CMSService();
        $console = CustomerSubscription::where('subscription_type_id', 1)->where('customer_id', $customer->id)->first();
        if($console){
            $cmsService->setConsoleSystemConfigs($console);
        }
    }
}
