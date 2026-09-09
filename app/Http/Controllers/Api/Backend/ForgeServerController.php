<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\ForgeServer;
use App\Services\ForgeServerSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ForgeServerController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name', 'ip_address', '#forge_server_id'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'forge_server_id', 'name', 'ip_address', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ForgeServer::class);

        $validated = $this->listFilters($request, [], self::SORTABLE);

        $query = ForgeServer::query();
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = ForgeServer::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ForgeServer::class);

        $row = ForgeServer::query()->create($request->validate([
            'forge_server_id' => ['required', 'integer', Rule::unique('my_forge_servers', 'forge_server_id')],
            'name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
        ]));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = ForgeServer::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'forge_server_id' => [
                'sometimes',
                'integer',
                Rule::unique('my_forge_servers', 'forge_server_id')->ignore($row->id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = ForgeServer::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function sync(): JsonResponse
    {
        $this->authorize('create', ForgeServer::class);

        try {
            $count = ForgeServerSyncService::syncFromApi();
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'ok' => true,
            'data' => ['synced' => $count],
        ]);
    }
}
