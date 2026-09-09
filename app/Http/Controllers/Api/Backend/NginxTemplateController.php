<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\NginxTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NginxTemplateController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name', 'server_id'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'name', 'server_id', 'template_id', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', NginxTemplate::class);

        $validated = $this->listFilters($request, [
            'server_id' => ['sometimes', 'integer'],
        ], self::SORTABLE);

        $query = NginxTemplate::query();
        if (array_key_exists('server_id', $validated)) {
            $query->where('server_id', $validated['server_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = NginxTemplate::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', NginxTemplate::class);

        $row = NginxTemplate::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'server_id' => ['required', 'integer'],
            'template_id' => ['required', 'integer'],
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = NginxTemplate::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'server_id' => ['sometimes', 'integer'],
            'template_id' => ['sometimes', 'integer'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = NginxTemplate::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
