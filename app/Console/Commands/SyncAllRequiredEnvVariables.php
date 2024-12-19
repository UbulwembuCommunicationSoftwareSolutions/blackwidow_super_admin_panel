<?php

namespace App\Console\Commands;

use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
use Illuminate\Console\Command;

class SyncAllRequiredEnvVariables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-all-required-env-variables';

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
            $addedEnv = EnvVariables::where('customer_subscription_id', $subscription->id)->pluck('key');
            $missing = RequiredEnvVariables::where('subscription_type_id', $subscription->subscription_type_id)
                ->whereNotIn('key', $addedEnv)
                ->get();
            $this->info('Subscription '.$subscription->url.' is missing '.count($missing).' required options');
            foreach($missing as $value){
                $this->info($subscription->url.' is missing '.$value->key.' adding it');
                EnvVariables::create([
                    'key' => $value->key,
                    'value' => $value->value,
                    'customer_subscription_id' => $subscription->id
                ]);
            }
        }
    }
}
