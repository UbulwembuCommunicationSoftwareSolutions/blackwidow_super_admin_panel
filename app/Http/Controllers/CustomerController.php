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

     private function cleanAppUrl(string $appUrl): string
    {
        // Remove http:// and https:// protocols
        $cleaned = preg_replace('/^https?:\/\//', '', $appUrl);
        $cleaned = preg_replace('/^http?:\/\//', '', $appUrl);
        
        // Remove trailing slash if present
        $cleaned = rtrim($cleaned, '/');
        
        return $cleaned;
    }
    
    private function findCustomerSubscriptionByUrl(string $appUrl): ?CustomerSubscription
    {
        $cleanedUrl = $this->cleanAppUrl($appUrl);
        
        return CustomerSubscription::where('url', 'LIKE', '%' . $cleanedUrl . '%')->first();
    }

    public function getUrls(Request $request)
    {
        $customerSub = $this->findCustomerSubscriptionByUrl($request->app_url);
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
