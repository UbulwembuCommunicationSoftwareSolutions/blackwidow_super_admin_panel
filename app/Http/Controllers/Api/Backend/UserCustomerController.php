<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\UserCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserCustomerController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['user.name', 'user.email', 'customer.company_name'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'user_id', 'customer_id', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserCustomer::class);

        $validated = $this->listFilters($request, [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'trashed' => ['sometimes', 'in:with,only'],
        ], self::SORTABLE);

        $query = UserCustomer::query()
            ->with(['user:id,name,email', 'customer:id,company_name']);

        $this->applyTrashed($query, $validated['trashed'] ?? null);

        if (array_key_exists('user_id', $validated)) {
            $query->where('user_id', $validated['user_id']);
        }
        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = UserCustomer::query()
            ->with(['user:id,name,email', 'customer:id,company_name'])
            ->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', UserCustomer::class);

        $row = UserCustomer::query()->create($request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]));

        return response()->json([
            'data' => $row->fresh()->load(['user:id,name,email', 'customer:id,company_name']),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = UserCustomer::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
        ]);
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json([
            'data' => $row->fresh()->load(['user:id,name,email', 'customer:id,company_name']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = UserCustomer::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function restore(int $id): JsonResponse
    {
        $row = UserCustomer::withTrashed()->findOrFail($id);
        $this->authorize('restore', $row);
        $row->restore();

        return response()->json([
            'data' => $row->fresh()->load(['user:id,name,email', 'customer:id,company_name']),
        ]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $row = UserCustomer::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $row);
        $row->forceDelete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
