<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\EnvVariables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvVariablesController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['key', 'value', 'customerSubscription.url'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'key', 'customer_subscription_id', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EnvVariables::class);

        $validated = $this->listFilters($request, [
            'customer_subscription_id' => ['sometimes', 'integer', 'exists:customer_subscriptions,id'],
        ], self::SORTABLE);

        $query = EnvVariables::query()->with('customerSubscription:id,url');
        if (array_key_exists('customer_subscription_id', $validated)) {
            $query->where('customer_subscription_id', $validated['customer_subscription_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('key'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EnvVariables::class);

        $row = EnvVariables::query()->create($request->validate([
            'customer_subscription_id' => ['required', 'integer', 'exists:customer_subscriptions,id'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'customer_subscription_id' => ['sometimes', 'integer', 'exists:customer_subscriptions,id'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = EnvVariables::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
