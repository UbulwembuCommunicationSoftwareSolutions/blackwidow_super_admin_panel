<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\Http;

class CMSService
{
    public function setConsoleSystemConfigs($subscription){
        $url = $subscription->url.'/api/admin-api/admin';
        $response = Http::withToken($subscription->customer->token)->get($url);
        dd($response->body());
    }

}
