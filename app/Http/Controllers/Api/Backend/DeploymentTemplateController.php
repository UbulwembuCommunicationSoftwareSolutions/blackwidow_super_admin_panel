<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\DeploymentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentTemplateController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['script', 'subscriptionType.name'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'subscription_type_id', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeploymentTemplate::class);

        $validated = $this->listFilters($request, [
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ], self::SORTABLE);

        $query = DeploymentTemplate::query()->with('subscriptionType:id,name');
        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = DeploymentTemplate::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DeploymentTemplate::class);

        $row = DeploymentTemplate::query()->create($request->validate([
            'script' => ['required', 'string'],
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = DeploymentTemplate::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'script' => ['sometimes', 'string'],
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = DeploymentTemplate::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
