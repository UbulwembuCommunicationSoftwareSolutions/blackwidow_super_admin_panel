<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Models\CustomerUser;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Services\EnvComparisonService;
use App\Services\SiteDeploymentScheduler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * JSON API for MCP / automation (auth: Sanctum personal access token).
 * List + CRUD; Customer hides secrets; CustomerSubscription can omit or include `env` on read.
 */
class McpSiteController extends Controller
{
    /** @var list<string> */
    private const CUSTOMER_MCP_HIDDEN = [
        'token',
        'google_api_key',
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_use_path_style_endpoint',
    ];

    /** @var list<string> */
    private const CUSTOMER_USER_MCP_HIDDEN = [
        'password',
        'sync_hash',
        'remember_token',
    ];

    /** @var list<string> */
    private const CUSTOMER_SORT_COLUMNS = ['id', 'company_name', 'created_at'];

    /** @var list<string> */
    private const SUBSCRIPTION_SORT_COLUMNS = ['id', 'url', 'domain', 'app_name', 'created_at', 'deployed_at'];

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
        ]);
    }

    public function overview(): JsonResponse
    {
        $customersTotal = Customer::query()->count();
        $customersTrashed = Customer::onlyTrashed()->count();
        $subscriptionsTotal = CustomerSubscription::query()->count();
        $subscriptionsDeployed = CustomerSubscription::query()->whereNotNull('deployed_at')->count();
        $subscriptionsNotDeployed = CustomerSubscription::query()->whereNull('deployed_at')->count();

        $byType = CustomerSubscription::query()
            ->selectRaw('subscription_type_id, count(*) as count')
            ->groupBy('subscription_type_id')
            ->with('subscriptionType:id,name')
            ->get()
            ->map(fn (CustomerSubscription $row) => [
                'subscription_type_id' => $row->subscription_type_id,
                'name' => $row->subscriptionType?->name,
                'count' => (int) $row->count,
            ])
            ->values();

        $atOrOverLimit = Customer::query()
            ->withCount('customerUsers')
            ->where('max_users', '>', 0)
            ->get()
            ->filter(fn (Customer $c) => $c->customer_users_count >= $c->max_users)
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'company_name' => $c->company_name,
                'max_users' => $c->max_users,
                'customer_users_count' => $c->customer_users_count,
            ])
            ->values();

        $jobCounts = CustomerSubscriptionDeploymentJob::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($c) => (int) $c)
            ->all();

        $deploymentJobsByStatus = [
            CustomerSubscriptionDeploymentJob::STATUS_PENDING => (int) ($jobCounts[CustomerSubscriptionDeploymentJob::STATUS_PENDING] ?? 0),
            CustomerSubscriptionDeploymentJob::STATUS_RUNNING => (int) ($jobCounts[CustomerSubscriptionDeploymentJob::STATUS_RUNNING] ?? 0),
            CustomerSubscriptionDeploymentJob::STATUS_COMPLETED => (int) ($jobCounts[CustomerSubscriptionDeploymentJob::STATUS_COMPLETED] ?? 0),
            CustomerSubscriptionDeploymentJob::STATUS_FAILED => (int) ($jobCounts[CustomerSubscriptionDeploymentJob::STATUS_FAILED] ?? 0),
        ];

        return response()->json([
            'customers' => [
                'total' => $customersTotal,
                'trashed' => $customersTrashed,
            ],
            'subscriptions' => [
                'total' => $subscriptionsTotal,
                'deployed' => $subscriptionsDeployed,
                'not_deployed' => $subscriptionsNotDeployed,
                'by_type' => $byType,
            ],
            'seat_utilisation' => [
                'customers_at_or_over_limit' => $atOrOverLimit,
                'count' => $atOrOverLimit->count(),
            ],
            'deployment_jobs_by_status' => $deploymentJobsByStatus,
        ]);
    }

    public function subscriptionTypes(): JsonResponse
    {
        $types = SubscriptionType::query()
            ->orderBy('id')
            ->get(['id', 'name', 'github_repo', 'project_type']);

        return response()->json([
            'data' => $types,
        ]);
    }

    public function templateEnvVariables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ]);

        $query = TemplateEnvVariables::query()
            ->orderBy('subscription_type_id')
            ->orderBy('key');

        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function showTemplateEnvVariable(int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);

        return response()->json(['data' => $row]);
    }

    public function storeTemplateEnvVariable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'requires_manual_fill' => ['boolean'],
            'admin_label' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
        ]);

        $row = TemplateEnvVariables::query()->create($validated);

        return response()->json(['data' => $row], 201);
    }

    public function updateTemplateEnvVariable(Request $request, int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);
        $validated = $request->validate([
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'requires_manual_fill' => ['boolean'],
            'admin_label' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
        ]);

        $row->update($validated);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyTemplateEnvVariable(int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function envVariables(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_subscription_id' => ['required', 'integer', 'exists:customer_subscriptions,id'],
        ]);

        $rows = EnvVariables::query()
            ->where('customer_subscription_id', $validated['customer_subscription_id'])
            ->orderBy('key')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function showEnvVariable(int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);

        return response()->json(['data' => $row]);
    }

    public function storeEnvVariable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_subscription_id' => ['required', 'integer', 'exists:customer_subscriptions,id'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        $row = EnvVariables::query()->create($validated);

        return response()->json(['data' => $row], 201);
    }

    public function updateEnvVariable(Request $request, int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);
        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]);

        $row->update($validated);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyEnvVariable(int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function customers(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request, extra: [
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(self::CUSTOMER_SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'with_counts' => ['sometimes', 'boolean'],
            'trashed' => ['sometimes', 'string', Rule::in(['with', 'only'])],
        ]);

        $query = Customer::query();
        $this->applyTrashedFilter($query, $validated['trashed'] ?? null);

        if (! empty($validated['search'])) {
            $query->where('company_name', 'like', '%'.$validated['search'].'%');
        }

        if (! empty($validated['with_counts'])) {
            $query->withCount(['customerSubscriptions', 'customerUsers']);
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $paginator = $query->paginate($validated['per_page']);

        $paginator->getCollection()->each(
            fn (Customer $c) => $c->makeHidden(self::CUSTOMER_MCP_HIDDEN)
        );

        return response()->json($paginator);
    }

    public function showCustomer(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $row->makeHidden(self::CUSTOMER_MCP_HIDDEN);

        return response()->json(['data' => $row]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $validated = McpMutationHelper::onlyCustomerSafe(
            $request->validate($this->customerMcpStoreRules())
        );
        $row = Customer::query()->create($validated);
        $row->makeHidden(self::CUSTOMER_MCP_HIDDEN);

        return response()->json(['data' => $row->fresh()->makeHidden(self::CUSTOMER_MCP_HIDDEN)], 201);
    }

    public function updateCustomer(Request $request, int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $validated = McpMutationHelper::onlyCustomerSafe(
            $request->validate($this->customerMcpUpdateRules())
        );
        if ($validated !== []) {
            $row->update($validated);
        }
        $row->makeHidden(self::CUSTOMER_MCP_HIDDEN);

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroyCustomer(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function customerSubscriptions(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request, extra: [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(self::SUBSCRIPTION_SORT_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'deployed' => ['sometimes', 'boolean'],
        ]);

        $query = CustomerSubscription::query()
            ->with([
                'subscriptionType:id,name',
                'customer:id,company_name',
            ]);

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }
        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }
        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('url', 'like', $term)
                    ->orWhere('domain', 'like', $term)
                    ->orWhere('app_name', 'like', $term);
            });
        }
        if (array_key_exists('deployed', $validated)) {
            if ($request->boolean('deployed')) {
                $query->whereNotNull('deployed_at');
            } else {
                $query->whereNull('deployed_at');
            }
        }

        $sort = $validated['sort'] ?? 'id';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);

        $paginator = $query->paginate($validated['per_page']);

        $paginator->getCollection()->each(
            fn (CustomerSubscription $s) => $s->makeHidden(['env', 'database_password'])
        );

        return response()->json($paginator);
    }

    public function showCustomerSubscription(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()
            ->with(['subscriptionType:id,name', 'customer:id,company_name'])
            ->findOrFail($id);

        $row->makeHidden('database_password');
        if (! $request->boolean('include_env')) {
            $row->makeHidden('env');
        }

        return response()->json(['data' => $row]);
    }

    public function storeCustomerSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate($this->customerSubscriptionCreateRules());
        $triggerSiteDeployment = (bool) ($validated['trigger_site_deployment'] ?? false);
        $forceSiteDeployment = (bool) ($validated['force_site_deployment'] ?? false);
        unset($validated['trigger_site_deployment'], $validated['force_site_deployment']);

        $row = CustomerSubscription::query()->create($validated);
        $row->makeHidden('database_password');
        if (! $request->boolean('include_env')) {
            $row->makeHidden('env');
        }
        $row->load(['subscriptionType:id,name', 'customer:id,company_name']);

        if ($triggerSiteDeployment) {
            try {
                app(SiteDeploymentScheduler::class)->schedule($row, $forceSiteDeployment);
            } catch (\RuntimeException $e) {
                $row->makeHidden('database_password');

                return response()->json([
                    'message' => $e->getMessage(),
                    'data' => $row,
                ], 409);
            }
        }

        $data = $row->fresh()->load(['subscriptionType:id,name', 'customer:id,company_name']);
        $data->makeHidden('database_password');
        if (! $request->boolean('include_env')) {
            $data->makeHidden('env');
        }

        return response()->json(['data' => $data], 201);
    }

    public function updateCustomerSubscription(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $validated = $request->validate($this->customerSubscriptionUpdateRules());
        if ($validated !== []) {
            $row->update($validated);
        }
        $row->load(['subscriptionType:id,name', 'customer:id,company_name']);

        $data = $row->fresh();
        $data->makeHidden('database_password');
        if (! $request->boolean('include_env')) {
            $data->makeHidden('env');
        }

        return response()->json(['data' => $data]);
    }

    public function destroyCustomerSubscription(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function envDiff(Request $request, int $id, EnvComparisonService $comparison): JsonResponse
    {
        $request->validate([
            'include_values' => ['sometimes', 'boolean'],
        ]);

        $subscription = CustomerSubscription::query()->findOrFail($id);
        $result = $comparison->compare($subscription, $request->boolean('include_values'));

        return response()->json(['data' => $result]);
    }

    public function customerUsers(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request, extra: [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'trashed' => ['sometimes', 'string', Rule::in(['with', 'only'])],
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

        $query = CustomerUser::query()
            ->with(['customer:id,company_name']);

        $this->applyTrashedFilter($query, $validated['trashed'] ?? null);

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }

        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email_address', 'like', $term);
            });
        }

        foreach ([
            'console_access',
            'firearm_access',
            'responder_access',
            'reporter_access',
            'security_access',
            'driver_access',
            'survey_access',
            'time_and_attendance_access',
            'stock_access',
        ] as $flag) {
            if (array_key_exists($flag, $validated)) {
                $query->where($flag, $request->boolean($flag));
            }
        }

        $paginator = $query->orderBy('id')->paginate($validated['per_page']);

        $paginator->getCollection()->each(
            fn (CustomerUser $u) => $u->makeHidden(self::CUSTOMER_USER_MCP_HIDDEN)
        );

        return response()->json($paginator);
    }

    public function showCustomerUser(int $id): JsonResponse
    {
        $row = CustomerUser::query()
            ->with(['customer:id,company_name'])
            ->findOrFail($id);
        $row->makeHidden(self::CUSTOMER_USER_MCP_HIDDEN);

        return response()->json(['data' => $row]);
    }

    public function deploymentJobs(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request, extra: [
            'customer_subscription_id' => ['sometimes', 'integer', 'exists:customer_subscriptions,id'],
            'status' => ['sometimes', 'string', Rule::in([
                CustomerSubscriptionDeploymentJob::STATUS_PENDING,
                CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
                CustomerSubscriptionDeploymentJob::STATUS_COMPLETED,
                CustomerSubscriptionDeploymentJob::STATUS_FAILED,
            ])],
            'batch_id' => ['sometimes', 'string', 'max:255'],
            'include_log' => ['sometimes', 'boolean'],
        ]);

        $query = CustomerSubscriptionDeploymentJob::query()
            ->with(['customerSubscription:id,customer_id,domain,url,app_name']);

        if (array_key_exists('customer_subscription_id', $validated)) {
            $query->where('customer_subscription_id', $validated['customer_subscription_id']);
        }
        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }
        if (array_key_exists('batch_id', $validated)) {
            $query->where('batch_id', $validated['batch_id']);
        }

        $paginator = $query->orderByDesc('id')->paginate($validated['per_page']);
        $includeLog = $request->boolean('include_log');

        $paginator->getCollection()->each(function (CustomerSubscriptionDeploymentJob $job) use ($includeLog) {
            if (! $includeLog) {
                $job->makeHidden('forge_log');
            }
        });

        return response()->json($paginator);
    }

    public function showDeploymentJob(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'include_log' => ['sometimes', 'boolean'],
        ]);

        $row = CustomerSubscriptionDeploymentJob::query()
            ->with(['customerSubscription:id,customer_id,domain,url,app_name'])
            ->findOrFail($id);

        if (! $request->boolean('include_log')) {
            $row->makeHidden('forge_log');
        }

        return response()->json(['data' => $row]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customerMcpStoreRules(): array
    {
        return array_merge(
            [
                'company_name' => ['required', 'string', 'max:255'],
            ],
            $this->customerMcpFieldRules('nullable')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function customerMcpUpdateRules(): array
    {
        $rules = $this->customerMcpFieldRules('sometimes');
        $rules['company_name'] = ['sometimes', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerMcpFieldRules(string $w): array
    {
        return [
            'max_users' => [$w, 'integer', 'min:0'],
            'docket_description' => [$w, 'string'],
            'task_description' => [$w, 'string'],
            'level_one_description' => [$w, 'string'],
            'level_two_description' => [$w, 'string'],
            'level_three_description' => [$w, 'string'],
            'level_four_description' => [$w, 'string'],
            'level_five_description' => [$w, 'string'],
            'level_one_in_use' => [$w, 'boolean'],
            'level_two_in_use' => [$w, 'boolean'],
            'level_three_in_use' => [$w, 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSubscriptionCreateRules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'domain' => ['required', 'string', 'max:255'],
            'database_name' => ['required', 'string', 'max:255'],
            'database_user' => ['nullable', 'string', 'max:32'],
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'server_id' => ['nullable', 'integer'],
            'logo_1' => ['nullable', 'string', 'max:500'],
            'logo_2' => ['nullable', 'string', 'max:500'],
            'logo_3' => ['nullable', 'string', 'max:500'],
            'logo_4' => ['nullable', 'string', 'max:500'],
            'logo_5' => ['nullable', 'string', 'max:500'],
            'env' => ['nullable', 'string'],
            'uuid' => ['nullable', 'string', 'max:36'],
            'forge_site_id' => ['nullable', 'string', 'max:100'],
            'app_name' => ['nullable', 'string', 'max:255'],
            'site_created_at' => ['nullable', 'date'],
            'github_sent_at' => ['nullable', 'date'],
            'env_sent_at' => ['nullable', 'date'],
            'deployment_script_sent_at' => ['nullable', 'date'],
            'ssl_deployed_at' => ['nullable', 'date'],
            'deployed_at' => ['nullable', 'date'],
            'panic_button_enabled' => ['nullable', 'boolean'],
            'deployed_version' => ['nullable', 'string', 'max:100'],
            'trigger_site_deployment' => ['sometimes', 'boolean'],
            'force_site_deployment' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSubscriptionUpdateRules(): array
    {
        $out = [];
        foreach ($this->customerSubscriptionCreateRules() as $key => $rule) {
            $out[$key] = array_merge(['sometimes'], array_slice($rule, 1));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function mcpListRules(Request $request, array $extra = []): array
    {
        $rules = array_merge([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], $extra);

        $validated = $request->validate($rules);

        if (! array_key_exists('per_page', $validated)) {
            $validated['per_page'] = 25;
        }

        return $validated;
    }

    private function applyTrashedFilter(Builder $query, ?string $trashed): void
    {
        if ($trashed === 'with') {
            $query->withTrashed();
        } elseif ($trashed === 'only') {
            $query->onlyTrashed();
        }
    }
}
