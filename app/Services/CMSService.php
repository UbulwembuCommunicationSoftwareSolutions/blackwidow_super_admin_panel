<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;

class CMSService
{
    public function setConsoleSystemConfigs($subscription){
        $url = $subscription->url.'/admin-api/set-levels';
        echo 'Doing request to '.$url.' with token '.$subscription->customer->token.PHP_EOL;
        $data = [
            'level_one_in_use' => $subscription->customer->level_one_in_use,
            'level_one_description' => $subscription->customer->level_one_description,
            'level_two_in_use' => $subscription->customer->level_two_in_use,
            'level_two_description' => $subscription->customer->level_two_description,
            'level_three_in_use' => $subscription->customer->level_three_in_use,
            'level_three_description' => $subscription->customer->level_three_description,
            'level_four_description' => $subscription->customer->level_four_description,
            'level_five_description' => $subscription->customer->level_five_description,
            'task_description' => $subscription->customer->task_description,
            'docket_description' => $subscription->customer->docket_description,
        ];

        $response = Http::withToken($subscription->customer->token)->post($url,$data);
        dd($response->body());
    }


    public function sendWelcomeEmail(CustomerUser $customerUser){
        $subscription = CustomerSubscription::where('subscription_type_id',1)
            ->where('customer_id',$customerUser->customer_id)
            ->first();
        $url = $subscription->url.'/admin-api/send-welcome-email';
        echo 'Doing request to '.$url.' with token '.$subscription->customer->token.PHP_EOL;
        $data = [
            'email' => $customerUser->email_address
        ];
        $response = Http::withToken($subscription->customer->token)->post($url,$data);
        \Log::info($response->body());
    }

}
