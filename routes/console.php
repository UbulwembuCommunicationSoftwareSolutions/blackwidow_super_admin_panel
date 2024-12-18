<?php

use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;



//
//
//Artisan::command('sendCommandToAllConsoles',function (){
//    $command = $this->ask('Enter Command');
//    $subscriptions = \App\Models\CustomerSubscription::where('subscription_type_id', 1)->get();
//    foreach($subscriptions as $subscription){
//        \App\Jobs\SendCommandToForge::dispatch($subscription->id,$command);
//    }
//
//})->purpose('Send Command To All Consoles');
//
//Artisan::command('sendCommandToAllResponders',function (){
//    $command = $this->ask('Enter Command');
//    $subscriptions = \App\Models\CustomerSubscription::where('subscription_type_id', 3)->get();
//    foreach($subscriptions as $subscription){
//        \App\Jobs\SendCommandToForge::dispatch($subscription->id,$command);
//    }
//
//})->purpose('Send Command To All Consoles');
//
//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote')->hourly();
//
//
//Artisan::command('syncForge', function () {
//    $this->info('Syncing Forge');
//    $forgeApi = new \App\Helpers\ForgeApi();
//    $forgeApi->syncForge();
//})->purpose('Sync Forge')->daily();
//
//
//Artisan::command('deployAllConsoles', function () {
//    $forgeApi = new \App\Helpers\ForgeApi();
//    $forgeApi->deployAllConsoles();
//
//})->purpose('Deploy All Consoles')->daily();
//
//Artisan::command('syncOneRequiredOptions', function () {
//    $required_options = \App\Models\RequiredEnvVariables::get();
//    $subscription = $this->ask('Enter Subscription ID');
//    $subscription = \App\Models\CustomerSubscription::find($subscription);
//    foreach($required_options as $option){
//        $subscription->envVariables()->updateOrCreate([
//            'key' => $option->key
//        ],[
//            'value' => $option->value
//        ]);
//    }
//    $APP_NAME = $subscription->envVariables()->where('key','APP_NAME')->first();
//    $APP_DB = $subscription->envVariables()->where('key','DB_DATABASE')->first();
//    $APP_URL = $subscription->envVariables()->where('key','APP_URL')->first();
//    $AWS_BUCKET = $subscription->envVariables()->where('key','AWS_BUCKET')->first();
//    $MINIO_BUCKET = $subscription->envVariables()->where('key','MINIO_BUCKET')->first();
//    $ELASTICSEARCH_INDEX = $subscription->envVariables()->where('key','ELASTICSEARCH_INDEX')->first();
//    $RESPONDER_APP_NAME = $subscription->envVariables()->where('key','RESPONDER_APP_NAME')->first();
//    $SECURITY_APP_NAME =  $subscription->envVariables()->where('key','SECURITY_APP_NAME')->first();
//    $DRIVER_APP_NAME = $subscription->envVariables()->where('key','DRIVER_APP_NAME')->first();
//
//    $APP_NAME->value = $subscription->app_name;
//    $APP_NAME->save();
//
//    $APP_DB->value = $subscription->database_name;
//    $APP_DB->save();
//
//    $APP_URL->value =$subscription->url;
//    $APP_URL->save();
//
//    $AWS_BUCKET->value = $APP_NAME->first()->value . 'bucket';
//    $AWS_BUCKET->save();
//    $MINIO_BUCKET->value = $APP_NAME->first()->value . 'bucket';
//    $MINIO_BUCKET->save();
//    $ELASTICSEARCH_INDEX->value = $APP_DB->first()->value . 'index';
//    $ELASTICSEARCH_INDEX->save();
//    $RESPONDER_APP_NAME->value =  $APP_NAME->first()->value . 'Responder';
//    $RESPONDER_APP_NAME->save();
//    $SECURITY_APP_NAME->value =  $APP_NAME->first()->value . 'Security';
//    $SECURITY_APP_NAME->save();
//    $DRIVER_APP_NAME->value =  $APP_NAME->first()->value . 'Driver';
//    $DRIVER_APP_NAME->save();
//
//});
//
//
//
//Artisan::command('syncAllRequiredOptionsForSubscription', function () {
//    \App\Models\CustomerSubscription::createMissingEnv();
//})->purpose('Sync All Required Options For Subscription');
//
//
//Artisan::command('sendEnvToSite',function (){
//    $subscription = $this->ask('Enter Subscription ID');
//    $forgeApi = new \App\Helpers\ForgeApi();
//    $forgeApi->sendEnv($subscription);
//})->purpose('Send Env To Site')->daily();
//
//Artisan::command('sendDeploymentToAllConsoles',function (){
//    $subscriptions = \App\Models\CustomerSubscription::where('subscription_type_id', 1)->get();
//    foreach($subscriptions as $subscription){
//        $job = \App\Jobs\SendDeploymentScriptToForge::dispatch($subscription->id);
//    }
//
//})->purpose('Send Deploymeny Script To All Consoles')->daily();
//
