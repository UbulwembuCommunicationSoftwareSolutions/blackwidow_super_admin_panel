<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BrandingSyncIndexRequest;
use App\Http\Requests\Api\V1\BrandingSyncUpsertRequest;
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

    public function upsert(BrandingSyncUpsertRequest $request): JsonResponse
    {
        $result = $this->sync->upsertFromTenant(
            $request->subscription(),
            $request->brandingPayload(),
        );

        $status = $result['outcome'] === BrandingSyncOutcome::Created ? 201 : 200;

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'branding' => $result['payload']->toArray(),
        ], $status);
    }
}
