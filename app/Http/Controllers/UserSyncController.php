<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncUsersWithSuperAdmin;
use App\Models\CustomerUser;
use App\Services\SuperAdminService;
use App\Services\UserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class UserSyncController extends Controller
{
    protected SuperAdminService $superAdminService;
    protected UserSyncService $userSyncService;

    public function __construct(SuperAdminService $superAdminService, UserSyncService $userSyncService)
    {
        $this->superAdminService = $superAdminService;
        $this->userSyncService = $userSyncService;
    }

    /**
     * Trigger user sync manually
     */
    public function triggerSync(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $force = $request->boolean('force', false);

            if (!config('services.superadmin.sync_enabled', true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User sync is disabled'
                ], 400);
            }

            if ($userId) {
                // Sync specific user
                $user = CustomerUser::find($userId);
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => "User with ID {$userId} not found"
                    ], 404);
                }

                $success = $this->userSyncService->syncUserToSuperAdmin($user);
                
                return response()->json([
                    'success' => $success,
                    'message' => $success ? 'User sync completed successfully' : 'User sync failed',
                    'user_id' => $userId,
                    'user_name' => "{$user->first_name} {$user->last_name}"
                ]);
            } else {
                // Full sync
                $exitCode = Artisan::call('app:sync-users-with-super-admin', [
                    '--force' => $force
                ]);

                $output = Artisan::output();

                return response()->json([
                    'success' => $exitCode === 0,
                    'message' => $exitCode === 0 ? 'Full sync completed successfully' : 'Full sync failed',
                    'output' => $output,
                    'exit_code' => $exitCode
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Manual user sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status for a user
     */
    public function getSyncStatus(Request $request, int $userId): JsonResponse
    {
        try {
            $user = CustomerUser::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => "User with ID {$userId} not found"
                ], 404);
            }

            $hasChanges = $this->userSyncService->hasLocalChanges($user);
            $shouldSkip = $this->userSyncService->shouldSkipSync($user);

            $recentLogs = $user->syncLogs()
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($log) {
                    return [
                        'direction' => $log->direction,
                        'status' => $log->status,
                        'synced_at' => $log->synced_at->toISOString(),
                        'error_message' => $log->error_message
                    ];
                });

            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'super_admin_user_id' => $user->super_admin_user_id,
                'last_synced_at' => $user->last_synced_at?->toISOString(),
                'has_local_changes' => $hasChanges,
                'should_skip_sync' => $shouldSkip,
                'recent_sync_logs' => $recentLogs
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync status', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync statistics
     */
    public function getSyncStats(): JsonResponse
    {
        try {
            $totalUsers = CustomerUser::whereNotNull('super_admin_user_id')->count();
            $usersNeedingSync = $this->userSyncService->getUsersNeedingSync(1000)->count();
            
            $recentLogs = \App\Models\UserSyncLog::recent(7)->get();
            $successfulSyncs = $recentLogs->where('status', 'success')->count();
            $failedSyncs = $recentLogs->where('status', 'failed')->count();
            $conflicts = $recentLogs->where('status', 'conflict')->count();

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_synced_users' => $totalUsers,
                    'users_needing_sync' => $usersNeedingSync,
                    'recent_syncs' => [
                        'successful' => $successfulSyncs,
                        'failed' => $failedSyncs,
                        'conflicts' => $conflicts,
                        'total' => $recentLogs->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync stats: ' . $e->getMessage()
            ], 500);
        }
    }
}
