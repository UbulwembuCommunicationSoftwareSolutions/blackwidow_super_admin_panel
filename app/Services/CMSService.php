<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\Http;

class CMSService
{
    public function setConsoleSystemConfigs($subscription){
        $url = $subscription->url.'/admin-api/admin';
        echo 'Doing request to '.$url.' with token '.$subscription->customer->token.PHP_EOL;
        $response = Http::withToken($subscription->customer->token)->post($url);
        dd($response->body());
    }

}
