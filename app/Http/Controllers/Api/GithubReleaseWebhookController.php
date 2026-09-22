<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGithubReleasesJob;
use App\Models\SubscriptionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GithubReleaseWebhookController extends Controller
{
    /**
     * Fast path for GitHub release events. The hourly SyncAllGithubReleasesJob remains the
     * source of truth; this only kicks a sync for matching subscription types.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $event = (string) $request->header('X-GitHub-Event', '');

        Log::info('github_webhook.request.received', [
            'delivery' => (string) $request->header('X-GitHub-Delivery', ''),
            'event' => $event,
            'action' => (string) $request->input('action', ''),
            'repository' => (string) data_get($request->all(), 'repository.full_name', ''),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'payload' => $request->all(),
        ]);

        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }

        if ($event !== 'release') {
            return response()->json(['ok' => true, 'ignored' => true, 'event' => $event]);
        }

        $action = (string) $request->input('action', '');
        if (! in_array($action, ['published', 'edited', 'deleted', 'prereleased', 'released', 'unpublished'], true)) {
            return response()->json(['ok' => true, 'ignored' => true, 'action' => $action]);
        }

        $fullName = (string) data_get($request->all(), 'repository.full_name', '');
        if ($fullName === '') {
            return response()->json(['message' => 'Missing repository.full_name'], 422);
        }

        $types = SubscriptionType::query()
            ->where(function ($query) use ($fullName): void {
                $query->where('github_repo', $fullName)
                    ->orWhere('github_repo', 'https://github.com/'.$fullName)
                    ->orWhere('github_repo', 'https://github.com/'.$fullName.'.git')
                    ->orWhere('github_repo', $fullName.'.git');
            })
            ->get(['id', 'github_repo']);

        foreach ($types as $type) {
            SyncGithubReleasesJob::dispatch((int) $type->id);
        }

        Log::info('github_webhook.release.received', [
            'action' => $action,
            'repository' => $fullName,
            'subscription_type_ids' => $types->pluck('id')->all(),
        ]);

        return response()->json([
            'ok' => true,
            'synced_types' => $types->count(),
        ]);
    }
}
