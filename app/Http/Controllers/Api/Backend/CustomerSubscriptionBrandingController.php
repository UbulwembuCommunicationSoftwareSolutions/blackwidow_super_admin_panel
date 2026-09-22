<?php

namespace App\Http\Controllers\Api\Backend;

use App\Http\Requests\Api\Backend\CustomerSubscriptionBrandSlotUpdateRequest;
use App\Models\BrandingSyncLog;
use App\Models\CustomerSubscription;
use App\Services\BrandingSync\CustomerBrandingMediaService;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\JsonResponse;

class CustomerSubscriptionBrandingController extends Controller
{
    public function __construct(private readonly CustomerBrandingMediaService $branding) {}

    public function show(int $id): JsonResponse
    {
        $subscription = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('view', $subscription);

        $payload = $this->branding->presentSubscriptionBranding($subscription);

        foreach (BrandingSyncPayload::SLOTS as $cmsSlot) {
            $lastLog = BrandingSyncLog::query()
                ->where('customer_subscription_id', $subscription->id)
                ->where('slot', $cmsSlot)
                ->where('direction', 'outbound')
                ->latest('synced_at')
                ->first();

            $payload['slots'][$cmsSlot]['sync_status'] = $lastLog?->status;
            $payload['slots'][$cmsSlot]['sync_error'] = $lastLog?->error_message;
            $payload['slots'][$cmsSlot]['synced_at'] = optional($lastLog?->synced_at)?->toIso8601String();
        }

        return response()->json(['data' => $payload]);
    }

    public function updateBrandSlot(
        CustomerSubscriptionBrandSlotUpdateRequest $request,
        int $id,
        string $slot,
    ): JsonResponse {
        if (! in_array($slot, BrandingSyncPayload::SLOTS, true)) {
            return response()->json(['message' => 'Unknown branding slot.'], 422);
        }

        $subscription = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $subscription);

        $validated = $request->validated();
        $payload = ['action' => $validated['action']];

        if ($validated['action'] === 'assign') {
            $payload['customer_branding_media_id'] = (int) $validated['customer_branding_media_id'];
        }

        if ($validated['action'] === 'upload') {
            $file = $request->file('file');
            if ($file === null) {
                return response()->json(['message' => 'File is required for upload action.'], 422);
            }
            $payload['file'] = $file;
        }

        $this->branding->updateSubscriptionBrandSlot($subscription, $slot, $payload);

        $fresh = CustomerSubscription::query()->findOrFail($id);
        $presented = $this->branding->presentSubscriptionBranding($fresh);

        return response()->json([
            'data' => [
                'slot' => $slot,
                'branding' => $presented,
                'logo_urls' => $fresh->logo_urls,
                'url' => $presented['slots'][$slot]['url'] ?? null,
                'source' => $presented['slots'][$slot]['source'] ?? null,
            ],
        ]);
    }
}
