<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerSyncIndexRequest;
use App\Models\Customer;
use App\Support\CustomerSync\CustomerSyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Customer directory sync for the shared multi-tenant LMS hub.
 */
class CustomerSyncController extends Controller
{
    public function index(CustomerSyncIndexRequest $request): JsonResponse
    {
        $customers = Customer::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => CustomerSyncPayload::fromCustomer($customer)->toArray())
            ->values()
            ->all();

        Log::debug('sync: customer hub list', [
            'app_url' => $request->input('app_url'),
            'count' => count($customers),
        ]);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }
}
