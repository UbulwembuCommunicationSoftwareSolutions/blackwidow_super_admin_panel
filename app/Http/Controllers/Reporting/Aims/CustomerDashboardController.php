<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Aims;

use App\Http\Controllers\Api\Backend\Controller;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use App\Services\Reporting\Aims\SqlName;
use App\Support\CustomerAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CustomerDashboardController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['id', 'customer_id', 'enabled', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerDashboard::class);

        $validated = $this->listFilters($request, [
            'customer_id' => ['sometimes', 'integer'],
            'dashboard_id' => ['sometimes', 'integer'],
        ], self::SORTABLE);

        $query = CustomerDashboard::query()->with($this->relations());
        $this->scopeToCustomerAdmin($query);

        if (isset($validated['customer_id'])) {
            if (! CustomerAdminAccess::allows($request->user(), $validated['customer_id'])) {
                abort(403);
            }
            $query->where('customer_id', $validated['customer_id']);
        }

        if (isset($validated['dashboard_id'])) {
            $query->where('dashboard_id', $validated['dashboard_id']);
        }

        $this->applySort($query, $validated, fn ($builder) => $builder->orderByDesc('id'));

        $page = $query->paginate($validated['per_page']);
        $page->getCollection()->transform(fn (CustomerDashboard $row) => $this->present($row));

        return response()->json($page);
    }

    public function show(int $id): JsonResponse
    {
        $row = $this->find($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $this->present($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerDashboard::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'dashboard_id' => ['required', 'integer', 'exists:dashboards,id'],
            'customer_subscription_id' => ['required', 'integer', 'exists:customer_subscriptions,id'],
            'reporting_database' => ['nullable', 'string', 'max:64'],
            'database_host' => ['nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $dashboard = Dashboard::query()->findOrFail($validated['dashboard_id']);
        $subscription = CustomerSubscription::query()->findOrFail($validated['customer_subscription_id']);
        $this->assertSubscriptionMatches($dashboard, $subscription, (int) $validated['customer_id']);

        if (filled($validated['reporting_database'] ?? null)) {
            $this->assertIdentifier($validated['reporting_database']);
        }

        $row = CustomerDashboard::query()->create([
            'customer_id' => $validated['customer_id'],
            'dashboard_id' => $dashboard->id,
            'customer_subscription_id' => $subscription->id,
            'reporting_database' => $validated['reporting_database']
                ?? CustomerDashboard::reportingDatabaseName((string) $subscription->database_name),
            'database_host' => $validated['database_host'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json(['data' => $this->present($row->load($this->relations()))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        $this->authorize('update', $row);

        if (CustomerAdminAccess::isCustomerAdmin($request->user())) {
            return response()->json(['message' => 'Customer admins can change user access only.'], 403);
        }

        $validated = $request->validate([
            'reporting_database' => ['sometimes', 'string', 'max:64'],
            'database_host' => ['nullable', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['reporting_database'])) {
            $this->assertIdentifier($validated['reporting_database']);
        }

        $row->update($validated);

        return response()->json(['data' => $this->present($row->fresh()->load($this->relations()))]);
    }

    public function syncUsers(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'customer_user_ids' => ['present', 'array'],
            'customer_user_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = $validated['customer_user_ids'];
        $count = CustomerUser::query()
            ->where('customer_id', $row->customer_id)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'customer_user_ids' => 'Every user must belong to this customer.',
            ]);
        }

        $row->customerUsers()->sync($ids);

        return response()->json(['data' => $this->present($row->fresh()->load($this->relations()))]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = $this->find($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    private function find(int $id): CustomerDashboard
    {
        $query = CustomerDashboard::query();
        $this->scopeToCustomerAdmin($query);

        return $query->findOrFail($id);
    }

    private function assertSubscriptionMatches(Dashboard $dashboard, CustomerSubscription $subscription, int $customerId): void
    {
        if ((int) $subscription->customer_id !== $customerId) {
            throw ValidationException::withMessages([
                'customer_subscription_id' => 'The subscription does not belong to this customer.',
            ]);
        }

        if ($dashboard->subscription_type_id !== null
            && (int) $subscription->subscription_type_id !== (int) $dashboard->subscription_type_id) {
            throw ValidationException::withMessages([
                'customer_subscription_id' => 'This dashboard reads a different subscription type.',
            ]);
        }

        if (blank($subscription->database_name)) {
            throw ValidationException::withMessages([
                'customer_subscription_id' => 'The subscription has no database name.',
            ]);
        }

        $already = CustomerDashboard::query()
            ->where('customer_subscription_id', $subscription->id)
            ->where('dashboard_id', $dashboard->id)
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'dashboard_id' => 'This dashboard is already enabled on that subscription.',
            ]);
        }
    }

    private function assertIdentifier(string $name): void
    {
        try {
            SqlName::assert($name);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'reporting_database' => 'Use letters, numbers and underscores only.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'dashboard:id,name,slug,description,subscription_type_id,handler',
            'customerSubscription:id,customer_id,url,domain,database_name,subscription_type_id,server_id',
            'customerUsers:id,customer_id,first_name,last_name,email_address',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomerDashboard $row): array
    {
        $row->loadMissing($this->relations());

        return [
            'id' => $row->id,
            'customer_id' => $row->customer_id,
            'dashboard_id' => $row->dashboard_id,
            'customer_subscription_id' => $row->customer_subscription_id,
            'reporting_database' => $row->reporting_database,
            'database_host' => $row->database_host,
            'enabled' => $row->enabled,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'dashboard' => $row->dashboard,
            'customer_subscription' => $row->customerSubscription?->only([
                'id', 'customer_id', 'url', 'domain', 'database_name', 'subscription_type_id', 'server_id',
            ]),
            'customer_users' => $row->customerUsers->map(fn (CustomerUser $user) => [
                'id' => $user->id,
                'customer_id' => $user->customer_id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email_address' => $user->email_address,
            ])->values(),
        ];
    }
}
