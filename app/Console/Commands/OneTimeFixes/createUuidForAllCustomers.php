<?php

namespace App\Console\Commands\OneTimeFixes;

use Str;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use Illuminate\Console\Command;

class createUuidForAllCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:createUuidForAllCustomers';

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
        $customers = CustomerSubscription::get();
        foreach ($customers as $customer) {
            $customer->uuid = Str::uuid();
            $customer->save();
        }
    }
}
