<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Aims;

use App\Http\Controllers\Api\Backend\Controller;
use App\Models\Reporting\Aims\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name', 'slug', 'handler', 'description'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'name', 'slug', 'handler', 'subscription_type_id', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Dashboard::class);

        $validated = $this->listFilters($request, [], self::SORTABLE);
        $query = Dashboard::query();
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($builder) => $builder->orderBy('name'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = Dashboard::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Dashboard::class);

        $row = Dashboard::query()->create($this->validated($request));

        return response()->json(['data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = Dashboard::query()->findOrFail($id);
        $this->authorize('update', $row);
        $row->update($this->validated($request, $row));

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = Dashboard::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Dashboard $existing = null): array
    {
        return $request->validate([
            'name' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [
                $existing ? 'sometimes' : 'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('dashboards', 'slug')->ignore($existing?->id),
            ],
            'description' => ['nullable', 'string'],
            'subscription_type_id' => ['nullable', 'integer', 'min:1'],
            'handler' => [$existing ? 'sometimes' : 'required', 'string', 'max:255', Rule::in([Dashboard::HANDLER_AIMS_CASES])],
        ]);
    }
}
