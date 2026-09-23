<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BrandingSyncIndexRequest;
use App\Http\Requests\Api\V1\BrandingSyncUpsertRequest;
use App\Http\Requests\Api\V1\CustomerSyncIndexRequest;
use App\Models\Customer;
use App\Services\BrandingSync\CustomerBrandingSyncService;
use App\Support\BrandingSync\BrandingSyncOutcome;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\JsonResponse;

/**
 * Canonical branding sync surface for tenant CMS apps.
 */
class BrandingSyncController extends Controller
{
    public function __construct(private readonly CustomerBrandingSyncService $sync) {}

    /**
     * Full set of branding slots for the tenant, for periodic reconciliation.
     */
    public function index(BrandingSyncIndexRequest $request): JsonResponse
    {
        $subscription = $request->subscription();

        $slots = collect(BrandingSyncPayload::SLOTS)
            ->map(fn (string $slot) => BrandingSyncPayload::fromSubscription($subscription, $slot)->toArray())
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'branding' => $slots,
        ]);
    }

    /**
     * Customer-default branding for every org, for the shared LMS hub reconcile.
     */
    public function hubIndex(CustomerSyncIndexRequest $request): JsonResponse
    {
        $data = Customer::query()
            ->orderBy('id')
            ->get()
            ->map(function (Customer $customer): array {
                return [
                    'super_admin_customer_id' => $customer->id,
                    'branding' => collect(BrandingSyncPayload::SLOTS)
                        ->map(fn (string $slot) => BrandingSyncPayload::fromCustomerDefault($customer, $slot)->toArray())
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function upsert(BrandingSyncUpsertRequest $request): JsonResponse
    {
        $payload = $request->brandingPayload();
        $superAdminCustomerId = $request->superAdminCustomerId();

        if ($superAdminCustomerId !== null || $request->validated('origin') === 'lms') {
            if ($superAdminCustomerId === null) {
                return response()->json([
                    'message' => 'branding.super_admin_customer_id is required for LMS hub branding.',
                ], 422);
            }

            $customer = Customer::query()->find($superAdminCustomerId);
            if ($customer === null) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            $result = $this->sync->upsertFromLmsHub($customer, $payload);
        } else {
            $result = $this->sync->upsertFromTenant(
                $request->subscription(),
                $payload,
            );
        }

        $status = $result['outcome'] === BrandingSyncOutcome::Created ? 201 : 200;

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'branding' => $result['payload']->toArray(),
        ], $status);
    }
}
