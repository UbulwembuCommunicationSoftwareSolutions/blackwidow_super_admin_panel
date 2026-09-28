<?php

namespace App\Http\Controllers\Api\Backend;

use App\Jobs\SyncGithubReleasesJob;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
            ->withCount(['deployedSubscriptions as sites_on_release'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (! ($validated['include_drafts'] ?? false)) {
            $query->published();
        }

        if ($validated['stable'] ?? false) {
            $query->stable();
        }

        $page = $query->paginate($validated['per_page'] ?? 50)->toArray();
        $page['current_release_id'] = $type->current_release_id;
        $page['last_synced_at'] = SubscriptionTypeRelease::query()
            ->where('subscription_type_id', $type->id)
            ->max('synced_at');

        return response()->json($page);
    }

    public function sync(int $id): JsonResponse
    {
        $type = SubscriptionType::query()->findOrFail($id);
        $this->authorize('update', $type);

        if (blank($type->github_repo)) {
            return response()->json([
                'message' => 'This subscription type has no GitHub repository configured.',
            ], 422);
        }

        try {
            SyncGithubReleasesJob::dispatchSync((int) $type->id);
        } catch (RequestException $e) {
            return response()->json([
                'message' => $this->githubErrorMessage($type, $e),
            ], 422);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Could not reach GitHub: '.$e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'type' => $type->fresh(['currentRelease', 'latestRelease']),
            'data' => SubscriptionTypeRelease::query()
                ->where('subscription_type_id', $type->id)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
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

    private function githubErrorMessage(SubscriptionType $type, RequestException $e): string
    {
        $status = $e->response->status();
        $githubMessage = (string) ($e->response->json('message') ?? '');

        return match (true) {
            $status === 401 => 'GitHub rejected the token (401). Check GITHUB_TOKEN on the API server.',
            $status === 403 => "GitHub refused access to {$type->github_repo} (403). ".($githubMessage ?: 'The token may lack repo scope or be rate limited.'),
            $status === 404 => "GitHub repository {$type->github_repo} was not found, or the token cannot see it (404).",
            default => "GitHub returned {$status}".($githubMessage !== '' ? ": {$githubMessage}" : '.'),
        };
    }
}
