<?php

namespace App\Http\Controllers\Api\Backend;

use App\Http\Requests\Api\Backend\CustomerBrandingMediaStoreRequest;
use App\Http\Requests\Api\Backend\CustomerBrandSlotUpdateRequest;
use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Services\BrandingSync\CustomerBrandingMediaService;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\JsonResponse;

class CustomerBrandingController extends Controller
{
    public function __construct(private readonly CustomerBrandingMediaService $branding) {}

    public function show(int $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $this->authorize('view', $customer);

        return response()->json(['data' => $this->branding->presentCustomerBranding($customer)]);
    }

    public function storeMedia(CustomerBrandingMediaStoreRequest $request, int $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $this->authorize('update', $customer);

        $validated = $request->validated();
        $file = $request->file('file');
        if ($file === null) {
            return response()->json(['message' => 'File is required.'], 422);
        }

        $media = $this->branding->storeUploadedMedia(
            $customer,
            $file,
            $validated['name'] ?? null,
        );

        if (filled($validated['slot'] ?? null)) {
            $this->branding->assignCustomerDefault($customer, (string) $validated['slot'], $media->id);
        }

        return response()->json([
            'data' => $this->branding->presentMedia($media->fresh(), $customer),
        ], 201);
    }

    public function updateBrandSlot(CustomerBrandSlotUpdateRequest $request, int $id, string $slot): JsonResponse
    {
        if (! in_array($slot, BrandingSyncPayload::SLOTS, true)) {
            return response()->json(['message' => 'Unknown branding slot.'], 422);
        }

        $customer = Customer::query()->findOrFail($id);
        $this->authorize('update', $customer);

        $mediaId = $request->validated('customer_branding_media_id');

        $row = $this->branding->assignCustomerDefault($customer, $slot, $mediaId !== null ? (int) $mediaId : null);

        return response()->json([
            'data' => [
                'slot' => $slot,
                'customer_branding_media_id' => $row->customer_branding_media_id,
                'url' => $row->media?->url(),
            ],
        ]);
    }

    public function destroyMedia(int $id, int $mediaId): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $this->authorize('update', $customer);

        $media = CustomerBrandingMedia::query()
            ->where('customer_id', $customer->id)
            ->whereKey($mediaId)
            ->firstOrFail();

        $this->branding->detachAndDeleteMedia($customer, $media);

        return response()->json(['ok' => true]);
    }
}
