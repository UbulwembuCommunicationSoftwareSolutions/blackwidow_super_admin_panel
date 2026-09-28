<?php

namespace App\Http\Controllers\Api\Backend;

use App\Http\Requests\Api\Backend\UpdateSubscriptionUserPermissionsRequest;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\Permissions\CustomerUserPermissionService;
use App\Services\Permissions\TenantPermissionCatalogService;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Granular tenant-app permissions per customer user, per customer subscription.
 */
class SubscriptionPermissionController extends Controller
{
    public function __construct(
        private readonly TenantPermissionCatalogService $catalog,
        private readonly CustomerUserPermissionService $grants,
    ) {}

    public function catalog(Request $request, int $id): JsonResponse
    {
        $subscription = $this->subscription($id);

        return response()->json([
            'data' => $this->catalog->catalog($subscription, $request->boolean('refresh')),
        ]);
    }

    public function matrix(int $id): JsonResponse
    {
        $subscription = $this->subscription($id);
        $this->authorize('viewAny', CustomerUser::class);

        $accessFlag = UserSyncPayload::flagForSubscriptionType((int) $subscription->subscription_type_id);

        $users = CustomerUser::query()
            ->where('customer_id', $subscription->customer_id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get();

        $grants = $this->grants->grantsFor($subscription, $users);
        $statuses = $this->grants->syncStatusFor($subscription, $users);

        return response()->json([
            'data' => [
                'subscription' => $this->presentSubscription($subscription),
                'users' => $users->map(fn (CustomerUser $user): array => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email_address' => $user->email_address,
                    'is_system_admin' => (bool) $user->is_system_admin,
                    'has_access' => (bool) $user->is_system_admin || ($accessFlag !== null && (bool) $user->{$accessFlag}),
                    'permissions' => $grants[$user->id] ?? [],
                    'sync' => $statuses[$user->id] ?? null,
                ])->all(),
            ],
        ]);
    }

    public function update(UpdateSubscriptionUserPermissionsRequest $request, int $id, int $userId): JsonResponse
    {
        $subscription = $this->subscription($id);
        $user = CustomerUser::query()->findOrFail($userId);

        abort_unless((int) $user->customer_id === (int) $subscription->customer_id, 403, 'This user does not belong to the subscription\'s customer.');

        $this->authorize('managePermissions', $user);

        $names = $this->grants->sync($user, $subscription, $request->validated('permissions', []));

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'customer_subscription_id' => $subscription->id,
                'permissions' => $names,
                'sync' => $this->grants->syncStatusFor($subscription, EloquentCollection::make([$user]))[$user->id] ?? null,
            ],
        ]);
    }

    private function subscription(int $id): CustomerSubscription
    {
        $subscription = CustomerSubscription::query()->with(['customer', 'subscriptionType'])->findOrFail($id);
        $this->authorize('view', $subscription);

        abort_unless($subscription->supportsPermissionSync(), 422, 'This subscription\'s app does not support permission management.');

        return $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSubscription(CustomerSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'customer_name' => $subscription->customer?->company_name,
            'url' => $subscription->url,
            'subscription_type_id' => $subscription->subscription_type_id,
            'subscription_type_name' => $subscription->subscriptionType?->name,
            'product' => $subscription->permissionProduct(),
        ];
    }
}
