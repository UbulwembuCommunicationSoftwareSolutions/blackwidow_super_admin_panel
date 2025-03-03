<?php

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Support\Facades\Http;

class CustomerSubscriptionService
{

    public static function getLogoDescriptions($subscriptionTypeID){
        //COMENT
        $logos = [
            'Login Logo',
            'Menu Logo',
            'Login Background',
            'Not Used',
            'Not Used'
        ];
        if((int)$subscriptionTypeID == 1){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 2){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 3){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 4){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 5){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 6){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 7){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 9){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        if((int)$subscriptionTypeID == 10){
            $logos = [
                'Login Logo',
                'Menu Logo',
                'Login Background',
                'Not Used',
                'Not Used'
            ];
        }
        return $logos;
    }

}
