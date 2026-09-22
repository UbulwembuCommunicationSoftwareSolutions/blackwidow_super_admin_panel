<?php

namespace App\Http\Controllers\Api\Backend;

use App\Jobs\SyncGithubReleasesJob;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionTypeReleaseController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        $type = SubscriptionType::query()->findOrFail($id);
        $this->authorize('view', $type);

        $validated = $request->validate([
            'stable' => ['sometimes', 'boolean'],
            'include_drafts' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SubscriptionTypeRelease::query()
            ->where('subscription_type_id', $type->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! ($validated['include_drafts'] ?? false)) {
            $query->published();
        }

        if ($validated['stable'] ?? false) {
            $query->stable();
        }

        return response()->json($query->paginate($validated['per_page'] ?? 50));
    }

    public function sync(int $id): JsonResponse
    {
        $type = SubscriptionType::query()->findOrFail($id);
        $this->authorize('update', $type);

        SyncGithubReleasesJob::dispatchSync((int) $type->id);

        return response()->json([
            'ok' => true,
            'data' => SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $type->id)
                ->orderByDesc('published_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function promote(int $id, int $release): JsonResponse
    {
        $type = SubscriptionType::query()->findOrFail($id);
        $this->authorize('update', $type);

        $row = SubscriptionTypeRelease::query()
            ->where('subscription_type_id', $type->id)
            ->whereKey($release)
            ->firstOrFail();

        if ($row->is_draft) {
            return response()->json([
                'message' => 'Cannot promote a release that is no longer on GitHub (marked draft).',
            ], 422);
        }

        $type->forceFill([
            'current_release_id' => $row->id,
            'master_version' => $row->tag,
        ])->save();

        return response()->json([
            'data' => $type->fresh(['currentRelease']),
        ]);
    }
}
