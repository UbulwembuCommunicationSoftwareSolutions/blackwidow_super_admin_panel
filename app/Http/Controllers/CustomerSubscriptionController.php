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
        $relativeBasePath = "pwa-icons/{$customerSubscription->id}/icons/";
        $fullPath = Storage::disk('public')->path($relativeBasePath);

        // Retrieve all files inside the icons directory
        $iconFiles = Storage::disk('public')->files($relativeBasePath);
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

    public function getSpecificLogo(Request $request)
    {
        $referer = $request->headers->get('referer');

        // Optionally, you can parse the referer to extract the host or domain
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';

        \Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%' . $originHost . '%')->first();
        if ($customerSubscription) {
            return redirect('https://superadmin.blackwidow.org.za/'.Storage::url($customerSubscription->logo_1));
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
