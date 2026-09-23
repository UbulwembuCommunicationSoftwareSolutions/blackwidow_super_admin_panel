<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerSyncIndexRequest;
use App\Http\Requests\Api\V1\UserFieldSyncIndexRequest;
use App\Http\Requests\Api\V1\UserFieldSyncUpsertRequest;
use App\Http\Requests\Api\V1\UserFieldValueSyncUpsertRequest;
use App\Models\Customer;
use App\Services\UserFieldSync\CustomerUserFieldSyncService;
use App\Support\UserFieldSync\UserFieldSyncOutcome;
use Illuminate\Http\JsonResponse;

class UserFieldSyncController extends Controller
{
    public function __construct(private readonly CustomerUserFieldSyncService $sync) {}

    public function index(UserFieldSyncIndexRequest $request): JsonResponse
    {
        $customer = $this->sync->customerFromSubscription($request->subscription());

        return response()->json([
            'success' => true,
            'user_fields' => $this->sync->listDefinitions($customer)
                ->map(fn ($payload) => $payload->toArray())
                ->values()
                ->all(),
        ]);
    }

    public function hubIndex(CustomerSyncIndexRequest $request): JsonResponse
    {
        $data = Customer::query()
            ->orderBy('id')
            ->get()
            ->map(function (Customer $customer): array {
                return [
                    'super_admin_customer_id' => $customer->id,
                    'user_fields' => $this->sync->listDefinitions($customer)
                        ->map(fn ($payload) => $payload->toArray())
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

    public function upsert(UserFieldSyncUpsertRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $result = $this->sync->upsertDefinition($customer, $request->userFieldPayload());
        $status = $result['outcome'] === UserFieldSyncOutcome::Created ? 201 : 200;

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'user_field' => $result['payload']->toArray(),
        ], $status);
    }

    public function archive(UserFieldSyncUpsertRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $result = $this->sync->archiveDefinition($customer, $request->userFieldPayload());

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'user_field' => $result['payload']->toArray(),
        ]);
    }

    public function restore(UserFieldSyncUpsertRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $result = $this->sync->restoreDefinition($customer, $request->userFieldPayload());

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'user_field' => $result['payload']->toArray(),
        ]);
    }

    public function valuesIndex(UserFieldSyncIndexRequest $request): JsonResponse
    {
        $customer = $this->sync->customerFromSubscription($request->subscription());

        return response()->json([
            'success' => true,
            'user_field_values' => $this->sync->listValues($customer)
                ->map(fn ($payload) => $payload->toArray())
                ->values()
                ->all(),
        ]);
    }

    public function valuesHubIndex(CustomerSyncIndexRequest $request): JsonResponse
    {
        $data = Customer::query()
            ->orderBy('id')
            ->get()
            ->map(function (Customer $customer): array {
                return [
                    'super_admin_customer_id' => $customer->id,
                    'user_field_values' => $this->sync->listValues($customer)
                        ->map(fn ($payload) => $payload->toArray())
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

    public function valuesUpsert(UserFieldValueSyncUpsertRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomerForValues($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $result = $this->sync->upsertValues($customer, $request->valuesPayload());
        $status = $result['outcome'] === UserFieldSyncOutcome::Created ? 201 : 200;

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'user_field_values' => $result['payload']->toArray(),
        ], $status);
    }

    private function resolveCustomer(UserFieldSyncUpsertRequest $request): Customer|JsonResponse
    {
        $superAdminCustomerId = $request->superAdminCustomerId();

        if ($superAdminCustomerId !== null || $request->validated('origin') === 'lms') {
            if ($superAdminCustomerId === null) {
                return response()->json([
                    'message' => 'user_field.super_admin_customer_id is required for LMS hub sync.',
                ], 422);
            }

            $customer = Customer::query()->find($superAdminCustomerId);
            if ($customer === null) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            return $customer;
        }

        return $this->sync->customerFromSubscription($request->subscription());
    }

    private function resolveCustomerForValues(UserFieldValueSyncUpsertRequest $request): Customer|JsonResponse
    {
        $superAdminCustomerId = $request->superAdminCustomerId();

        if ($superAdminCustomerId !== null || $request->validated('origin') === 'lms') {
            if ($superAdminCustomerId === null) {
                return response()->json([
                    'message' => 'user_field_values.super_admin_customer_id is required for LMS hub sync.',
                ], 422);
            }

            $customer = Customer::query()->find($superAdminCustomerId);
            if ($customer === null) {
                return response()->json(['message' => 'Customer not found.'], 404);
            }

            return $customer;
        }

        return $this->sync->customerFromSubscription($request->subscription());
    }
}
