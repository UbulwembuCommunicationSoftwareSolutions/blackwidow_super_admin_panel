<?php

namespace App\Http\Controllers\SystemsApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class SystemsController extends Controller
{
    public function getSystemDescriptions(Request $request){
        $customerSubscription = CustomerSubscription::where('url', $request->app_url)->first();
        return new CustomerResource($customerSubscription->customer);
    }
    public function setSystemDescriptions(Request $request){
        $customerSubscription = CustomerSubscription::where('url', $request->app_url)->first();
        return new CustomerResource($customerSubscription->customer);
    }
}
