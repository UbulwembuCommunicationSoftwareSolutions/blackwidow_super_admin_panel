<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\TemplateEnvVariables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateEnvVariablesController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['key', 'value', 'admin_label', 'help_text', 'subscriptionType.name'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'key', 'value', 'admin_label', 'requires_manual_fill', 'subscription_type_id', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TemplateEnvVariables::class);

        $validated = $this->listFilters($request, [
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ], self::SORTABLE);

        $query = TemplateEnvVariables::query()->with('subscriptionType:id,name');

        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);

        // Counted off the filtered query before sorting and paging, so the header
        // reads "N keys · M manual" for the whole filter rather than one page.
        $counted = clone $query;

        $this->applySort(
            $query,
            $validated,
            fn ($q) => $q->orderBy('subscription_type_id')->orderBy('key'),
        );

        return response()->json(array_merge($query->paginate($validated['per_page'])->toArray(), [
            'summary' => [
                'total' => (clone $counted)->count(),
                'manual' => $counted->where('requires_manual_fill', true)->count(),
            ],
        ]));
    }

    public function show(int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TemplateEnvVariables::class);

        $row = TemplateEnvVariables::query()->create($request->validate([
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'requires_manual_fill' => ['sometimes', 'boolean'],
            'admin_label' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'requires_manual_fill' => ['sometimes', 'boolean'],
            'admin_label' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = TemplateEnvVariables::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
