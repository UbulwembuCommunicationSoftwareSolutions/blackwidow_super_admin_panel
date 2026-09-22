<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionTypeController extends Controller
{
    /** @var list<string> */
    private const SEARCHABLE = ['name', 'github_repo', 'branch', 'project_type', 'master_version'];

    /** @var list<string> */
    private const SORTABLE = ['id', 'name', 'github_repo', 'branch', 'project_type', 'master_version', 'created_at', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SubscriptionType::class);

        $validated = $this->listFilters($request, [
            'trashed' => ['sometimes', 'in:with,only'],
        ], self::SORTABLE);

        $query = SubscriptionType::query()->with('currentRelease:id,tag,commit_sha,is_prerelease,published_at');
        $this->applyTrashed($query, $validated['trashed'] ?? null);
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        return response()->json($query->paginate($validated['per_page']));
    }

    public function show(int $id): JsonResponse
    {
        $row = SubscriptionType::query()->with('currentRelease')->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SubscriptionType::class);

        $row = SubscriptionType::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'github_repo' => ['required', 'string', 'max:255'],
            'branch' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', 'max:255'],
            'master_version' => ['nullable', 'string', 'max:64'],
            'public_dir' => ['nullable', 'string', 'max:255'],
            'current_release_id' => ['nullable', 'integer', 'exists:subscription_type_releases,id'],
            'auto_promote_stable' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $row->load('currentRelease')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = SubscriptionType::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'github_repo' => ['sometimes', 'string', 'max:255'],
            'branch' => ['sometimes', 'string', 'max:255'],
            'project_type' => ['sometimes', 'string', 'max:255'],
            'master_version' => ['nullable', 'string', 'max:64'],
            'public_dir' => ['nullable', 'string', 'max:255'],
            'current_release_id' => ['nullable', 'integer', 'exists:subscription_type_releases,id'],
            'auto_promote_stable' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('current_release_id', $validated) && $validated['current_release_id'] !== null) {
            $belongs = SubscriptionTypeRelease::query()
                ->whereKey($validated['current_release_id'])
                ->where('subscription_type_id', $row->id)
                ->exists();
            if (! $belongs) {
                return response()->json(['message' => 'current_release_id must belong to this subscription type.'], 422);
            }
        }

        if ($validated !== []) {
            $row->update($validated);
            if (array_key_exists('current_release_id', $validated)) {
                $row->load('currentRelease');
                if ($row->currentRelease) {
                    $row->forceFill(['master_version' => $row->currentRelease->tag])->save();
                }
            }
        }

        return response()->json(['data' => $row->fresh(['currentRelease'])]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = SubscriptionType::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function restore(int $id): JsonResponse
    {
        $row = SubscriptionType::withTrashed()->findOrFail($id);
        $this->authorize('restore', $row);
        $row->restore();

        return response()->json(['data' => $row->fresh()]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $row = SubscriptionType::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $row);
        $row->forceDelete();

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
