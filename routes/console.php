<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('syncForge', function () {
    $this->info('Syncing Forge');
    $forgeApi = new \App\Helpers\ForgeApi();
    $forgeApi->syncForge();
})->purpose('Sync Forge')->daily();


Artisan::command('syncAllRequiredOptions', function () {
    $required_options = \Illuminate\Support\Facades\DB::select('select DISTINCT(env_variables.key) from env_variables
JOIN customer_subscriptions on customer_subscriptions.id = env_variables.customer_subscription_id
where customer_subscriptions.subscription_type_id="1"');
    foreach($required_options as $option){
        $option = (array)$option;
        $option = $option['key'];
        $required_option = \App\Models\RequiredEnvVariables::where('key', $option)->first();
        if(!$required_option){
            $required_option = new \App\Models\RequiredEnvVariables();
            $required_option->key = $option;
            $required_option->value = '';
            $required_option->subscription_type_id = 1;
            $required_option->save();
        }
    }

})->purpose('Sync All Required Options')->daily();

Artisan::command('syncAllRequiredOptionsForSubscription', function () {
    \App\Models\CustomerSubscription::createMissingEnv();
})->purpose('Sync All Required Options For Subscription');
