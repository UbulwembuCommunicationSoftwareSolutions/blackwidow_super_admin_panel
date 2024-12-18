<?php

namespace App\Http\Controllers;

use App\Helpers\ForgeApi;
use App\Http\Requests\CustomerSubscriptionRequest;
use App\Http\Resources\CustomerSubscriptionResource;
use App\Models\CustomerSubscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerSubscriptionController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        return CustomerSubscriptionResource::collection(CustomerSubscription::all());
    }

    public function getLogos(Request $request)
    {
        if($request->has('customer_url')){
            $url = $request->customer_url;
            \Log::info('URL: '.$url);
            $customerSubscription = CustomerSubscription::where('url', $request->customer_url)->first();
            if($customerSubscription){
                return response()->json([
                    "logo_1" => $customerSubscription->logo_1,
                    "logo_2" => $customerSubscription->logo_2,
                    "logo_3" => $customerSubscription->logo_3,
                    "logo_4" => $customerSubscription->logo_4,
                    "logo_5" => $customerSubscription->logo_5,
                ]);
            }else{
                return response()->json($logos = []);
            }

        }else{
            return response()->json($logos = []);
        }
    }

    public function getSpecificLogo(Request $request)
    {
        $referer = $request->headers->get('referer');

        // Optionally, you can parse the referer to extract the host or domain
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';

        \Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->first();
        if ($customerSubscription) {
            return redirect(Storage::url($customerSubscription->logo_1));
        }else{
            return response()->json([
                'status' => 'ERROR',
                'message' => 'No logo found for this customer'
            ], 404);
        }
    }

    public function store(CustomerSubscriptionRequest $request)
    {
        $this->authorize('create', CustomerSubscription::class);

        return new CustomerSubscriptionResource(CustomerSubscription::create($request->validated()));
    }

    public function show(CustomerSubscription $customerSubscription)
    {
        $forgeApi =  new ForgeApi();
        dd($forgeApi);
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
