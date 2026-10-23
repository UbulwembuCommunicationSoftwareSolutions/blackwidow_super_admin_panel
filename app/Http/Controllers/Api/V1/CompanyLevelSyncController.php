<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompanyLevelSyncUpsertRequest;
use Illuminate\Http\JsonResponse;

/**
 * CMS is where operators edit organisational levels. This stores that save on
 * the customer so a later system-config push does not put the old flags back.
 */
class CompanyLevelSyncController extends Controller
{
    public function upsert(CompanyLevelSyncUpsertRequest $request): JsonResponse
    {
        $customer = $request->subscription()?->customer;

        if ($customer === null) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        // The console already has these values. Pushing them back would overwrite
        // the save that just called us.
        $customer->skipSystemConfigPush = true;
        $customer->fill($request->customerAttributes());
        $customer->save();

        return response()->json([
            'success' => true,
        ]);
    }
}
