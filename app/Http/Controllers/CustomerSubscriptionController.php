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

    public function checkLoggedIn(Request $request)
    {
        $user = \Auth::user();
        if (!$user) {
            \Log::info('User is not logged in');
            return response()->json([
                'status' => 'error',
                'message' => 'User is not logged in'
            ], 401);
        }else{
            \Log::info('User is logged in');
            return response()->json([
                'status' => 'success',
                'message' => 'User is logged in',
                'user' => $user,
                'token' => $request->bearerToken()
            ]);
        }
    }
    public function getManifest(Request $request)
    {
        // Get the referer URL
        $referer = $request->headers->get('referer');
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';

        // Find the customer's subscription based on the domain
        $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->first();

        if (!$customerSubscription) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        // Define the icons directory
        $relativeBasePath = "pwa-icons/{$customerSubscription->id}/icons";
        $fullPath = Storage::disk('public')->path($relativeBasePath);

        // Retrieve all files inside the icons directory
        $iconFiles = Storage::disk('public')->files($relativeBasePath);
        \Log::info("Icon Path: ".($relativeBasePath));
        \Log::info(json_encode($iconFiles));
        $icons = [];

        // Loop through each file and extract icon information
        foreach ($iconFiles as $file) {
            $filename = basename($file);

            // Extract size from filename (e.g., icon-192x192.png → 192x192)
            if (preg_match('/(\d+x\d+)/', $filename, $matches)) {
                $size = $matches[1]; // Extracted size
            } else {
                continue; // Skip files without size info
            }

            // Determine file type (favicon.ico has a different type)
            $fileType = (str_ends_with($filename, '.ico')) ? 'image/x-icon' : 'image/png';

            // Determine purpose (maskable icons)
            $purpose = str_contains($filename, 'maskable') ? 'maskable' : 'any';

            // Construct the icon entry
            $icons[] = [
                "src" => asset(Storage::url($file)),
                "sizes" => $size,
                "type" => $fileType,
                "purpose" => $purpose
            ];
        }

        // Construct the manifest array
        $manifest = [
            "name" => $customerSubscription->app_name,
            "short_name" => $customerSubscription->app_name,
            "start_url" => $customerSubscription->url,
            "display" => "standalone",
            "background_color" => "#000000",
            "theme_color" => "#000000",
            "icons" => $icons
        ];

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json']);
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
    public function getResponderAppFunctions(Request $request)
    {
        $customerUrl = $request->get('customer_api_url');
        \Log::info('URL: '.$customerUrl);
        $parsedUrl = parse_url($customerUrl, PHP_URL_HOST);
        $originHost = $parsedUrl;
        \Log::info("Query: ".CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->toRawSql());
        \Log::info('Origin Host: '.$originHost);
        $customerApiSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')
            ->first();
        $customerSubscription = CustomerSubscription::where('subscription_type_id',3)
            ->where('customer_id', $customerApiSubscription->customer_id)
            ->first();
        if ($customerSubscription) {
            return response()->json([
                'status' => 'success',
                'message' => 'App functions retrieved successfully',
                'app_functions' => $customerSubscription,
                'deployedVersion' => $customerSubscription->deployed_version,
                'masterVersion' => $customerSubscription->subscriptionType->master_version,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'No app functions found for this customer'
            ], 404);
        }
    }
    public function getAppFunctions(Request $request){
        $referer = $request->headers->get('referer');
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';
        \Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->first();
        if ($customerSubscription) {
            return response()->json([
                'status' => 'success',
                'message' => 'App functions retrieved successfully',
                'app_functions' => $customerSubscription,
                'deployedVersion' => $customerSubscription->deployed_version,
                'masterVersion' => $customerSubscription->subscriptionType->master_version,
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'No app functions found for this customer'
            ], 404);
        }
    }

    public function getSpecificLogo(Request $request)
    {
        $referer = $request->headers->get('referer');

        // Optionally, you can parse the referer to extract the host or domain
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';
        \Log::info("Query: ".CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->toRawSql());
        \Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->first();
        if ($customerSubscription) {
            $logoPath = 'https://superadmin.blackwidow.org.za/'.Storage::url($customerSubscription->logo_1);
            \Log::info('Logo Path: '.$logoPath);
            return redirect($logoPath);
        }else{
            \Log::info('No subscription found for this URL: '.$originHost);
            \Log::info("Query: ".CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->toRawSql());
            return response()->json([
                'status' => 'ERROR',
                'message' => 'No logo found for this customer'
            ], 404);
        }
    }


    public function getSubscriptionLogo(Request $request){
        if($request->has('subscription_id')){
            \Log::info("RECEIVED SUBSCRIPTION ID: ".$request->subscription_id);
            $customerSubscription = CustomerSubscription::where('customer_subscriptions.uuid', $request->subscription_id)->first();
        }
        if($request->has('logo_id')){
            $logoId = $request->logo_id;
        }
        switch ($logoId) {
            case 1:
                $logoField = 'logo_1';
                break;
            case 2:
                $logoField = 'logo_2';
                break;
            case 3:
                $logoField = 'logo_3';
                break;
            case 4:
                $logoField = 'logo_4';
                break;
            case 5:
                $logoField = 'logo_5';
                break;
            default:
                $logoField = 'logo_1';
                break;
        }
        $logoPath = 'https://superadmin.blackwidow.org.za/'.Storage::url($customerSubscription->$logoField);
        return response()->json(['logo' => $logoPath]);

    }
    public function getSingleLogo(Request $request)
    {
        if($request->has('subscription_id')){
            \Log::info("RECEIVED SUBSCRIPTION ID: ".$request->subscription_id);
            $customerSubscription = CustomerSubscription::where('customer_subscriptions.uuid', $request->subscription_id)->first();
        }
        if(!$customerSubscription) {
            $customerUrl = $request->get('customer_api_url');
            \Log::info('URL: ' . $customerUrl);
            $parsedUrl = parse_url($customerUrl, PHP_URL_HOST);
            $originHost = $parsedUrl;
            \Log::info("Query: " . CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->toRawSql());
            \Log::info('Origin Host: ' . $originHost);
            $customerApiSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')
                ->first();
            $customerSubscription = CustomerSubscription::where('subscription_type_id', 3)
                ->where('customer_id', $customerApiSubscription->customer_id)
                ->first();
        }
        if ($customerSubscription) {
            $logoPath = 'https://superadmin.blackwidow.org.za/'.Storage::url($customerSubscription->logo_1);
            return response()->json(['logo' => $logoPath]);
        }else{
            \Log::info('No subscription found for this URL: '.$originHost);
            \Log::info("Query: ".CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->toRawSql());
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
