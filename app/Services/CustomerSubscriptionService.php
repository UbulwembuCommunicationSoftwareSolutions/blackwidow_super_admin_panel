<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;

class CustomerSubscriptionService
{

    public static function getLogoDescriptions($subscription){
        if((int)$subscription->subscription_type_id == 1){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 2){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 3){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 4){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 5){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 6){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 7){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 9){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscription->subscription_type_id == 10){
            $logos[] = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        return false;
    }

}
