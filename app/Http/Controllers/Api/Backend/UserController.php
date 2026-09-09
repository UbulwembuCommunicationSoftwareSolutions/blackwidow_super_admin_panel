<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name', 'email'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $this->listFilters($request, [], self::SORTABLE);

        $query = User::query()->with('roles:id,name');
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = User::query()->with('roles:id,name')->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate($this->storeRules());
        $roles = $validated['roles'] ?? [];
        unset($validated['roles']);

        $row = User::query()->create($validated);
        if ($roles !== []) {
            $row->syncRoles($roles);
        }

        return response()->json(['data' => $row->fresh()->load('roles:id,name')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = User::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate($this->updateRules($row));
        $roles = $validated['roles'] ?? null;
        unset($validated['roles']);

        if (array_key_exists('password', $validated) && blank($validated['password'])) {
            unset($validated['password']);
        }

        if ($validated !== []) {
            $row->update($validated);
        }
        if (is_array($roles)) {
            $row->syncRoles($roles);
        }

        return response()->json(['data' => $row->fresh()->load('roles:id,name')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = User::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['required'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(User $row): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($row->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'max:255'],
            'email_verified_at' => ['nullable', 'date'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['required'],
        ];
    }
}
