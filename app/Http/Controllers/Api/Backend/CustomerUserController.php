<?php

namespace App\Http\Controllers\Api\Backend;

use App\Jobs\SendSubscriptionEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\CMSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerUserController extends Controller
{
    /** @var list<string> */
    private const HIDDEN = ['password', 'remember_token', 'sync_hash'];

    /** @var list<string> */
    private const SEARCHABLE = ['email_address', 'first_name', 'last_name', 'cellphone'];

    /** @var list<string> */
    private const SORTABLE = [
        'id', 'email_address', 'first_name', 'last_name', 'cellphone',
        'customer_id', 'is_system_admin', 'created_at', 'updated_at', 'deleted_at', 'delete_scheduled',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerUser::class);

        $validated = $this->listFilters($request, [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'trashed' => ['sometimes', 'in:with,only'],
        ], self::SORTABLE);

        $query = CustomerUser::query();
        $this->applyTrashed($query, $validated['trashed'] ?? null);

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        $paginator = $query->paginate($validated['per_page']);
        $paginator->getCollection()->each(
            fn (CustomerUser $row) => $row->makeHidden(self::HIDDEN)
        );

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row->makeHidden(self::HIDDEN)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerUser::class);

        $row = CustomerUser::query()->create($request->validate($this->storeRules()));

        return response()->json(['data' => $row->fresh()->makeHidden(self::HIDDEN)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate($this->updateRules($row));
        if (array_key_exists('password', $validated) && blank($validated['password'])) {
            unset($validated['password']);
        }
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()->makeHidden(self::HIDDEN)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->scheduleDelete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function restore(int $id): JsonResponse
    {
        $row = CustomerUser::withTrashed()->findOrFail($id);
        $this->authorize('restore', $row);
        $row->clearDeleteSchedule();

        return response()->json(['data' => $row->fresh()->makeHidden(self::HIDDEN)]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $row = CustomerUser::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $row);
        $row->forceDelete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string', 'same:new_password'],
        ]);

        $row->password = $validated['new_password'];
        $row->save();

        return response()->json(['ok' => true, 'data' => $row->fresh()->makeHidden(self::HIDDEN)]);
    }

    public function sendWelcomeEmail(int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        SendWelcomeEmailJob::dispatch($row);

        return response()->json(['ok' => true]);
    }

    public function sendLoginEmail(Request $request, int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
        ]);

        $subscription = CustomerSubscription::query()
            ->where('customer_id', $row->customer_id)
            ->where('subscription_type_id', $validated['subscription_type_id'])
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found for this customer.'], 422);
        }

        if (! $row->checkAccess($validated['subscription_type_id'])) {
            return response()->json(['message' => 'User does not have access to this subscription.'], 422);
        }

        SendSubscriptionEmailJob::dispatch($row, $subscription);

        return response()->json(['ok' => true]);
    }

    public function impersonate(Request $request, int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'customer_subscription_id' => ['required', 'integer', 'exists:customer_subscriptions,id'],
        ]);

        $subscription = CustomerSubscription::query()
            ->where('customer_id', $row->customer_id)
            ->find($validated['customer_subscription_id']);

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found for this customer.'], 422);
        }

        // Console only for now - other tenant apps don't expose the mint-token endpoint yet.
        if ((int) $subscription->subscription_type_id !== 1) {
            return response()->json(['message' => 'Impersonation currently only supports Console subscriptions.'], 422);
        }

        if (! $row->checkAccess($subscription->subscription_type_id)) {
            return response()->json(['message' => 'User does not have access to this subscription.'], 422);
        }

        try {
            $result = app(CMSService::class)->impersonate($row, $subscription);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        Log::info('Customer user impersonation link issued', [
            'admin_user_id' => $request->user()?->id,
            'customer_user_id' => $row->id,
            'customer_subscription_id' => $subscription->id,
        ]);

        return response()->json($result);
    }

    public function updateAccessRights(Request $request, int $id): JsonResponse
    {
        $row = CustomerUser::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'is_system_admin' => ['sometimes', 'boolean'],
            'console_access' => ['sometimes', 'boolean'],
            'firearm_access' => ['sometimes', 'boolean'],
            'responder_access' => ['sometimes', 'boolean'],
            'reporter_access' => ['sometimes', 'boolean'],
            'security_access' => ['sometimes', 'boolean'],
            'driver_access' => ['sometimes', 'boolean'],
            'survey_access' => ['sometimes', 'boolean'],
            'time_and_attendance_access' => ['sometimes', 'boolean'],
            'stock_access' => ['sometimes', 'boolean'],
        ]);

        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()->makeHidden(self::HIDDEN)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email_address' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customer_users', 'email_address')->where(
                    fn ($query) => $query->where('customer_id', request('customer_id'))
                ),
            ],
            'password' => ['required', 'string', 'min:6'],
            'cellphone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('customer_users', 'cellphone')->where(
                    fn ($query) => $query->where('customer_id', request('customer_id'))
                ),
            ],
            ...$this->accessRules('sometimes'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(CustomerUser $row): array
    {
        return [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email_address' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('customer_users', 'email_address')
                    ->where(fn ($query) => $query->where('customer_id', request('customer_id', $row->customer_id)))
                    ->ignore($row->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'cellphone' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('customer_users', 'cellphone')
                    ->where(fn ($query) => $query->where('customer_id', request('customer_id', $row->customer_id)))
                    ->ignore($row->id),
            ],
            ...$this->accessRules('sometimes'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accessRules(string $presence): array
    {
        return [
            'is_system_admin' => [$presence, 'boolean'],
            'console_access' => [$presence, 'boolean'],
            'firearm_access' => [$presence, 'boolean'],
            'responder_access' => [$presence, 'boolean'],
            'reporter_access' => [$presence, 'boolean'],
            'security_access' => [$presence, 'boolean'],
            'driver_access' => [$presence, 'boolean'],
            'survey_access' => [$presence, 'boolean'],
            'time_and_attendance_access' => [$presence, 'boolean'],
            'stock_access' => [$presence, 'boolean'],
        ];
    }
}
