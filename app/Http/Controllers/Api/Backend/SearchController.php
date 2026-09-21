<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DeploymentTemplate;
use App\Models\NginxTemplate;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use App\Support\CustomerAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Topbar global search. Fans out across the handful of resources an operator
 * jumps to by name, and returns them grouped and ready to render.
 *
 * Each group is gated on its own ViewAny policy and skipped — not refused —
 * when the operator lacks it, so a restricted account gets a smaller result
 * set rather than a 403 for the whole search.
 */
class SearchController extends Controller
{
    /** Rows returned per group. One more than this is fetched to detect overflow. */
    private const PER_GROUP = 5;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $gate = Gate::forUser($request->user());
        $groups = [];

        foreach ($this->resources() as $resource) {
            if (! $gate->allows('viewAny', $resource['model'])) {
                continue;
            }

            $query = $resource['model']::query();

            if ($resource['with'] !== []) {
                $query->with($resource['with']);
            }

            $customerId = CustomerAdminAccess::customerId($request->user());
            if ($customerId !== null) {
                if ($resource['model'] === Customer::class) {
                    $query->whereKey($customerId);
                } elseif ($resource['model'] === CustomerSubscription::class) {
                    $query->where('customer_id', $customerId);
                }
            }

            $this->applySearch($query, $validated['q'], $resource['columns']);

            $rows = $query->orderBy('id')->limit(self::PER_GROUP + 1)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $groups[] = [
                'type' => $resource['type'],
                'label' => $resource['label'],
                'has_more' => $rows->count() > self::PER_GROUP,
                'results' => $rows
                    ->take(self::PER_GROUP)
                    ->map($resource['map'])
                    ->values(),
            ];
        }

        return response()->json(['data' => $groups]);
    }

    /**
     * Search targets, in the order they are shown. Columns follow the same
     * syntax as the per-list `search` parameter, so global and in-list search
     * agree on what a term matches.
     *
     * @return list<array<string, mixed>>
     */
    private function resources(): array
    {
        return [
            [
                'type' => 'customer',
                'label' => 'Customers',
                'model' => Customer::class,
                'columns' => ['company_name', 'uuid'],
                'with' => [],
                'map' => fn (Customer $row) => [
                    'id' => $row->id,
                    'title' => $row->company_name ?: "Customer #{$row->id}",
                    'subtitle' => null,
                ],
            ],
            [
                'type' => 'subscription',
                'label' => 'Subscriptions',
                'model' => CustomerSubscription::class,
                'columns' => ['url', 'domain', 'app_name', 'customer.company_name'],
                'with' => ['customer:id,company_name'],
                'map' => fn (CustomerSubscription $row) => [
                    'id' => $row->id,
                    'title' => $row->url ?: $row->domain ?: $row->app_name ?: "Subscription #{$row->id}",
                    'subtitle' => $row->customer?->company_name,
                ],
            ],
            [
                'type' => 'subscription-type',
                'label' => 'Subscription types',
                'model' => SubscriptionType::class,
                'columns' => ['name', 'github_repo'],
                'with' => [],
                'map' => fn (SubscriptionType $row) => [
                    'id' => $row->id,
                    'title' => $row->name ?: "Type #{$row->id}",
                    'subtitle' => $row->github_repo,
                ],
            ],
            [
                'type' => 'template-env-variable',
                'label' => 'Template env variables',
                'model' => TemplateEnvVariables::class,
                'columns' => ['key', 'subscriptionType.name'],
                'with' => ['subscriptionType:id,name'],
                'map' => fn (TemplateEnvVariables $row) => [
                    'id' => $row->id,
                    'title' => $row->key ?: "Key #{$row->id}",
                    'subtitle' => $row->subscriptionType?->name,
                ],
            ],
            [
                'type' => 'nginx-template',
                'label' => 'Nginx templates',
                'model' => NginxTemplate::class,
                'columns' => ['name'],
                'with' => [],
                'map' => fn (NginxTemplate $row) => [
                    'id' => $row->id,
                    'title' => $row->name ?: "Template #{$row->id}",
                    'subtitle' => $row->server_id ? "server {$row->server_id}" : null,
                ],
            ],
            [
                // Filament shows the model label here because the resource sets
                // no record title. The subscription type name is the useful one.
                'type' => 'deployment-template',
                'label' => 'Deployment templates',
                'model' => DeploymentTemplate::class,
                'columns' => ['subscriptionType.name'],
                'with' => ['subscriptionType:id,name'],
                'map' => fn (DeploymentTemplate $row) => [
                    'id' => $row->id,
                    'title' => $row->subscriptionType?->name ?: "Deployment template #{$row->id}",
                    'subtitle' => null,
                ],
            ],
        ];
    }
}
