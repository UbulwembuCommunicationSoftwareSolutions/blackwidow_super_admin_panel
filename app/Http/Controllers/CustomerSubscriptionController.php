<?php

namespace App\Http\Controllers;

use App\Helpers\ForgeApi;
use App\Http\Requests\CustomerSubscriptionRequest;
use App\Http\Resources\CustomerSubscriptionResource;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Log;

class CustomerSubscriptionController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        return CustomerSubscriptionResource::collection(CustomerSubscription::all());
    }

    public function checkLoggedIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_url' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof CustomerUser || $user->isDeleteScheduled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access Denied',
            ], 401);
        }

        $url = $this->normalizeAppUrl($validated['app_url']);
        $subscription = CustomerSubscription::query()->where('url', $url)->first();

        if (! $subscription || (int) $subscription->customer_id !== (int) $user->customer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access Denied',
            ], 401);
        }

        if (! $user->checkAccess((int) $subscription->subscription_type_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access Denied',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User is logged in',
            'user' => $user->makeHidden(['password']),
            'token' => $request->bearerToken(),
        ]);
    }

    public function ssoLogout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out',
        ]);
    }

    private function normalizeAppUrl(string $url): string
    {
        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, strlen('http://'));
        }

        return $url;
    }

    public function getManifest(Request $request): JsonResponse
    {
        $originHost = $this->resolveManifestHost($request);

        if ($originHost === null) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        $customerSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->first();

        if (! $customerSubscription) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        // Define the icons directory
        $relativeBasePath = "pwa-icons/{$customerSubscription->id}/icons";
        $fullPath = Storage::disk('public')->path($relativeBasePath);

        // Retrieve all files inside the icons directory
        $iconFiles = Storage::disk('public')->files($relativeBasePath);
        Log::info('Icon Path: '.($relativeBasePath));
        Log::info(json_encode($iconFiles));
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
                'src' => asset(Storage::url($file)),
                'sizes' => $size,
                'type' => $fileType,
                'purpose' => $purpose,
            ];
        }

        // Construct the manifest array
        $manifest = [
            'name' => $customerSubscription->app_name,
            'short_name' => $customerSubscription->app_name,
            'start_url' => $customerSubscription->url,
            'display' => 'standalone',
            'background_color' => '#000000',
            'theme_color' => '#000000',
            'icons' => $icons,
        ];

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json']);
    }

    private function resolveManifestHost(Request $request): ?string
    {
        foreach ([$request->headers->get('referer'), $request->headers->get('origin')] as $candidate) {
            if (! filled($candidate)) {
                continue;
            }

            $host = parse_url((string) $candidate, PHP_URL_HOST);
            if (filled($host)) {
                return $host;
            }
        }

        if (! $request->filled('customer_url')) {
            return null;
        }

        $rawUrl = (string) $request->query('customer_url');
        $host = parse_url($rawUrl, PHP_URL_HOST);
        if (filled($host)) {
            return $host;
        }

        if ($rawUrl !== '' && ! str_contains($rawUrl, '://')) {
            $host = parse_url('https://'.$rawUrl, PHP_URL_HOST);
            if (filled($host)) {
                return $host;
            }
        }

        return null;
    }

    public function getLogos(Request $request): JsonResponse
    {
        $customerSubscription = null;

        if ($request->filled('customer_api_url')) {
            $originHost = parse_url((string) $request->query('customer_api_url'), PHP_URL_HOST);
            if (! $originHost) {
                return response()->json(new \stdClass);
            }

            Log::info('getLogos customer_api_url host: '.$originHost);
            $customerSubscription = $this->findSubscriptionForLogosByHost((string) $originHost);
        } elseif ($request->filled('customer_url')) {
            $rawUrl = (string) $request->query('customer_url');
            Log::info('getLogos customer_url: '.$rawUrl);
            $normalized = rtrim($rawUrl, '/');

            $customerSubscription = CustomerSubscription::query()
                ->where(function ($q) use ($rawUrl, $normalized) {
                    $q->where('url', $rawUrl)
                        ->orWhere('url', $normalized)
                        ->orWhere('url', $normalized.'/');
                })
                ->first();

            if (! $customerSubscription) {
                $host = parse_url($normalized, PHP_URL_HOST);
                if (! $host && $normalized !== '') {
                    $host = parse_url('https://'.$normalized, PHP_URL_HOST);
                }
                if ($host) {
                    $customerSubscription = $this->findSubscriptionForLogosByHost($host);
                }
            }
        }

        if (! $customerSubscription) {
            return response()->json(new \stdClass);
        }

        return response()->json([
            'logo_1' => $this->logoPathToAbsoluteUrl($customerSubscription->logo_1),
            'logo_2' => $this->logoPathToAbsoluteUrl($customerSubscription->logo_2),
            'logo_3' => $this->logoPathToAbsoluteUrl($customerSubscription->logo_3),
            'logo_4' => $this->logoPathToAbsoluteUrl($customerSubscription->logo_4),
            'logo_5' => $this->logoPathToAbsoluteUrl($customerSubscription->logo_5),
            'logo_1_updated_at' => $customerSubscription->logo_1_updated_at?->toIso8601String(),
            'logo_2_updated_at' => $customerSubscription->logo_2_updated_at?->toIso8601String(),
            'logo_3_updated_at' => $customerSubscription->logo_3_updated_at?->toIso8601String(),
            'logo_4_updated_at' => $customerSubscription->logo_4_updated_at?->toIso8601String(),
            'logo_5_updated_at' => $customerSubscription->logo_5_updated_at?->toIso8601String(),
        ]);
    }

    public function getCmsUrl(Request $request): JsonResponse
    {
        $customerUrl = $request->query('customer_api_url');
        if (! filled($customerUrl)) {
            return response()->json([
                'error' => 'customer_api_url parameter is required',
            ], 422);
        }

        $originHost = parse_url((string) $customerUrl, PHP_URL_HOST);
        if (! $originHost) {
            return response()->json([
                'error' => 'Could not determine host from customer_api_url',
            ], 422);
        }

        Log::info('getCmsUrl origin host: '.$originHost);

        $customerApiSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')
            ->first();

        if (! $customerApiSubscription) {
            return response()->json([
                'error' => 'No subscription found for this host',
            ], 404);
        }

        $cmsSubscription = CustomerSubscription::where('customer_id', $customerApiSubscription->customer_id)
            ->where('subscription_type_id', 1)
            ->first();

        if (! $cmsSubscription) {
            return response()->json([
                'error' => 'No CMS subscription found for this customer',
            ], 404);
        }

        $cmsUrl = rtrim((string) $cmsSubscription->url, '/');

        return response()->json([
            'cms_url' => $cmsUrl,
        ]);
    }

    public function getResponderAppFunctions(Request $request)
    {
        $customerUrl = $request->get('customer_api_url');
        Log::info('URL: '.$customerUrl);
        $parsedUrl = parse_url($customerUrl, PHP_URL_HOST);
        $originHost = $parsedUrl;
        Log::info('Query: '.CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->toRawSql());
        Log::info('Origin Host: '.$originHost);
        $customerApiSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')
            ->first();
        $customerSubscription = CustomerSubscription::where('subscription_type_id', 3)
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
                'message' => 'No app functions found for this customer',
            ], 404);
        }
    }

    public function getAppFunctions(Request $request)
    {
        $referer = $request->headers->get('referer');
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';
        Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->first();
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
                'message' => 'No app functions found for this customer',
            ], 404);
        }
    }

    public function getSpecificLogo(Request $request)
    {
        $referer = $request->headers->get('referer');

        // Optionally, you can parse the referer to extract the host or domain
        $parsedUrl = parse_url($referer);
        $originHost = $parsedUrl['host'] ?? 'unknown';
        Log::info('Query: '.CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->toRawSql());
        Log::info('Referer: '.$originHost);
        $customerSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->first();
        if ($customerSubscription) {
            $logoPath = $this->absolutePublicStorageUrl($customerSubscription->logo_1);
            Log::info('Logo Path: '.$logoPath);

            return redirect($logoPath);
        } else {
            Log::info('No subscription found for this URL: '.$originHost);
            Log::info('Query: '.CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->toRawSql());

            return response()->json([
                'status' => 'ERROR',
                'message' => 'No logo found for this customer',
            ], 404);
        }
    }

    public function getSubscriptionLogo(Request $request)
    {
        if ($request->has('subscription_id')) {
            Log::info('RECEIVED SUBSCRIPTION ID: '.$request->subscription_id);
            $customerSubscription = CustomerSubscription::where('customer_subscriptions.uuid', $request->subscription_id)->first();
        }
        if ($request->has('logo_id')) {
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
        $logoPath = $this->absolutePublicStorageUrl($customerSubscription->$logoField);

        return response()->json(['logo' => $logoPath]);

    }

    public function getSingleLogo(Request $request)
    {
        if ($request->has('subscription_id')) {
            Log::info('RECEIVED SUBSCRIPTION ID: '.$request->subscription_id);
            $customerSubscription = CustomerSubscription::where('customer_subscriptions.uuid', $request->subscription_id)->first();
        }
        if (! $customerSubscription) {
            $customerUrl = $request->get('customer_api_url');
            Log::info('URL: '.$customerUrl);
            $parsedUrl = parse_url($customerUrl, PHP_URL_HOST);
            $originHost = $parsedUrl;
            Log::info('Query: '.CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->toRawSql());
            Log::info('Origin Host: '.$originHost);
            $customerApiSubscription = CustomerSubscription::where('url', 'like', '%'.$originHost.'%')
                ->first();
            $customerSubscription = CustomerSubscription::where('subscription_type_id', 3)
                ->where('customer_id', $customerApiSubscription->customer_id)
                ->first();
        }
        if ($customerSubscription) {
            $logoPath = $this->absolutePublicStorageUrl($customerSubscription->logo_1);

            return response()->json(['logo' => $logoPath]);
        } else {
            Log::info('No subscription found for this URL: '.$originHost);
            Log::info('Query: '.CustomerSubscription::where('url', 'like', '%'.$originHost.'%')->toRawSql());

            return response()->json([
                'status' => 'ERROR',
                'message' => 'No logo found for this customer',
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
        $forgeApi = new ForgeApi;
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

    /**
     * Join APP_URL with Storage::url() without a double slash (Storage::url returns /storage/...).
     */
    private function absolutePublicStorageUrl(?string $relativePath): string
    {
        return rtrim(config('app.url'), '/').Storage::url($relativePath);
    }

    /**
     * Prefer Responder app subscription (type 3) when multiple rows match the same host.
     */
    private function findSubscriptionForLogosByHost(string $host): ?CustomerSubscription
    {
        return CustomerSubscription::query()
            ->where('url', 'like', '%'.$host.'%')
            ->orderByRaw('(subscription_type_id = ?) DESC', [3])
            ->first();
    }

    private function logoPathToAbsoluteUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        return $this->absolutePublicStorageUrl($relativePath);
    }
}
