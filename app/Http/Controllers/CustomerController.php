<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Customer::class);

        return CustomerResource::collection(Customer::all());
    }

    public function getUrls(Request $request)
    {
        $customerSub = CustomerSubscription::where('url', $request->app_url)->first();
        $customer = Customer::find($customerSub->customer_id);
        $urls = [];
        foreach($customer->customerSubscriptions as $subscription){
            $urls[] = array(
                'type' => $subscription->subscriptionType->name,
                'url' => $subscription->url
            );
        }
        return response()->json($urls);
    }

    public function store(CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        return new CustomerResource(Customer::create($request->validated()));
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }



    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json();
    }
}
