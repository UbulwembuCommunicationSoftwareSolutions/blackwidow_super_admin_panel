<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerSubscriptionRequest;
use App\Http\Resources\CustomerSubscriptionResource;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CustomerSubscriptionController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        return CustomerSubscriptionResource::collection(CustomerSubscription::all());
    }

    public function getLogoOne(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            return response()->json([
                $customerSubscription->logo_1,
            ]);
        }else{
            return response()->json([
                "message" => "Customer URL is required"
            ], 400);
        }
    }

    public function getLogoTwo(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            return response()->json([
                $customerSubscription->logo_2,
            ]);
        }else{
            return response()->json([
                "message" => "Customer URL is required"
            ], 400);
        }
    }

    public function getLogoThree(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            return response()->json([
                $customerSubscription->logo_3,
            ]);
        }else{
            return response()->json([
                "message" => "Customer URL is required"
            ], 400);
        }
    }

    public function getLogoFour(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            return response()->json([
                $customerSubscription->logo_4,
            ]);
        }else{
            return response()->json([
                "message" => "Customer URL is required"
            ], 400);
        }
    }

    public function getLogoFive(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            return response()->json([
                $customerSubscription->logo_5,
            ]);
        }else{
            return response()->json([
                "message" => "Customer URL is required"
            ], 400);
        }
    }

    public function store(CustomerSubscriptionRequest $request)
    {
        $this->authorize('create', CustomerSubscription::class);

        return new CustomerSubscriptionResource(CustomerSubscription::create($request->validated()));
    }

    public function show(CustomerSubscription $customerSubscription)
    {
        $this->authorize('view', $customerSubscription);

        return new CustomerSubscriptionResource($customerSubscription);
    }

    public function update(CustomerSubscriptionRequest $request, CustomerSubscription $customerSubscription)
    {
        $this->authorize('update', $customerSubscription);

        $customerSubscription->update($request->validated());

        return new CustomerSubscriptionResource($customerSubscription);
    }

    public function destroy(CustomerSubscription $customerSubscription)
    {
        $this->authorize('delete', $customerSubscription);

        $customerSubscription->delete();

        return response()->json();
    }
}
