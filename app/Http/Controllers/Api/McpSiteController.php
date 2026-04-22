<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionType;
use Illuminate\Http\JsonResponse;

/**
 * Read-only JSON endpoints for MCP and other machine clients (auth: Sanctum personal access token).
 */
class McpSiteController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
        ]);
    }

    public function subscriptionTypes(): JsonResponse
    {
        $types = SubscriptionType::query()
            ->orderBy('id')
            ->get(['id', 'name', 'github_repo', 'project_type']);

        return response()->json([
            'data' => $types,
        ]);
    }
}
