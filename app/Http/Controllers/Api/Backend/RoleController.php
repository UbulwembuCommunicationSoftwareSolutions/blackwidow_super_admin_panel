<?php

namespace App\Http\Controllers\Api\Backend;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'name', 'created_at', 'updated_at'];

    /**
     * Dual-mode index:
     * - No pagination params → unpaginated picker `{ data: [{id,name}] }` for the user form
     *   (gated on ViewAny:User OR ViewAny:Role so existing environments keep working).
     * - With page/per_page → paginated CRUD list gated on ViewAny:Role.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $request->has('page') || $request->has('per_page') || $request->has('search') || $request->has('sort');

        if (! $paginated) {
            $user = $request->user();
            abort_unless($user->can('ViewAny:User') || $user->can('ViewAny:Role'), 403);

            return response()->json([
                'data' => Role::query()
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ]);
        }

        $this->authorize('viewAny', Role::class);

        $validated = $this->listFilters($request, [], self::SORTABLE);

        $query = $this->roleQuery()->withCount(['permissions', 'users']);
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('name'));

        $paginator = $query->paginate($validated['per_page']);
        $paginator->getCollection()->transform(fn (Role $role) => $this->present($role));

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->roleQuery()->with('permissions:id,name')->withCount(['permissions', 'users'])->findOrFail($id);
        $this->authorize('view', $role);

        return response()->json(['data' => $this->present($role, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $role->load('permissions:id,name')->loadCount(['permissions', 'users']);

        return response()->json(['data' => $this->present($role, true)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::query()->findOrFail($id);
        $this->authorize('update', $role);

        $superAdmin = $this->superAdminName();
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', Rule::exists('permissions', 'name')],
        ]);

        if (
            array_key_exists('name', $validated)
            && $validated['name'] !== $role->name
            && $role->name === $superAdmin
        ) {
            throw ValidationException::withMessages([
                'name' => ['The super_admin role cannot be renamed.'],
            ]);
        }

        if (array_key_exists('name', $validated) && $role->name !== $superAdmin) {
            $role->name = $validated['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions']);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $role->load('permissions:id,name')->loadCount(['permissions', 'users']);

        return response()->json(['data' => $this->present($role, true)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::query()->findOrFail($id);
        $this->authorize('delete', $role);

        if ($role->name === $this->superAdminName()) {
            throw ValidationException::withMessages([
                'name' => ['The super_admin role cannot be deleted.'],
            ]);
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * Permission matrix source for the roles form, grouped by model.
     */
    public function permissions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $grouped = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(function (string $name) {
                $parts = explode(':', $name, 2);

                return $parts[1] ?? 'Other';
            })
            ->map(fn ($names, $model) => [
                'model' => $model,
                'permissions' => $names->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $grouped]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Role $role, bool $withPermissions = false): array
    {
        $payload = [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => (int) ($role->permissions_count ?? $role->permissions()->count()),
            'users_count' => (int) ($role->users_count ?? $role->users()->count()),
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'is_super_admin' => $role->name === $this->superAdminName(),
        ];

        if ($withPermissions) {
            $payload['permissions'] = $role->relationLoaded('permissions')
                ? $role->permissions->pluck('name')->values()->all()
                : $role->permissions()->pluck('name')->values()->all();
        }

        return $payload;
    }

    private function superAdminName(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }

    /**
     * Fresh Role models inherit the current auth default guard (sanctum during
     * token auth), which has no user provider — so withCount('users') blows up.
     * Pin guard_name to web for relation resolution.
     *
     * @return Builder<Role>
     */
    private function roleQuery(): Builder
    {
        return (new Role(['guard_name' => 'web']))->newQuery();
    }
}
