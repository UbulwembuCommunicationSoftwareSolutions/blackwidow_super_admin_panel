<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only JSON endpoints for MCP and other machine clients (auth: Sanctum personal access token).
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

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
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

    public function customers(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request);

        $paginator = Customer::query()
            ->orderBy('id')
            ->paginate($validated['per_page']);

        $paginator->getCollection()->each(
            fn (Customer $c) => $c->makeHidden(self::CUSTOMER_MCP_HIDDEN)
        );

        return response()->json($paginator);
    }

    public function customerSubscriptions(Request $request): JsonResponse
    {
        $validated = $this->mcpListRules($request, extra: [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ]);

        $query = CustomerSubscription::query()
            ->with([
                'subscriptionType:id,name',
                'customer:id,company_name',
            ])
            ->orderBy('id');

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }
        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }

        $paginator = $query->paginate($validated['per_page']);

        $paginator->getCollection()->each(
            fn (CustomerSubscription $s) => $s->makeHidden('env')
        );

        return response()->json($paginator);
    }

    /**
     * @param  array<string, mixed>  $extra
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
}
