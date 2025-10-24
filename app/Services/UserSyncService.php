<?php

namespace App\Services;

use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSyncService
{
    protected SuperAdminService $superAdminService;

    public function __construct(SuperAdminService $superAdminService)
    {
        $this->superAdminService = $superAdminService;
    }

    /**
     * Sync user to SuperAdmin
     */
    public function syncUserToSuperAdmin(CustomerUser $user): bool
    {
        if (!$this->shouldSyncUser($user)) {
            return false;
        }

        try {
            $response = $this->superAdminService->updateUser($user);
            
            // Update sync tracking fields
            $user->update([
                'last_synced_at' => Carbon::now(),
                'sync_hash' => $this->generateSyncHash($user),
            ]);

            // Log successful sync
            $this->logSync($user, 'outbound', 'success', null, $response);

            return true;
        } catch (\Exception $e) {
            // Log failed sync
            $this->logSync($user, 'outbound', 'failed', $e->getMessage());
            
            Log::error('Failed to sync user to SuperAdmin', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate hash of syncable fields
     */
    public function generateSyncHash(CustomerUser $user): string
    {
        $syncableFields = [
            'first_name',
            'last_name',
            'email_address',
            'cellphone',
            'console_access',
            'firearm_access',
            'responder_access',
            'reporter_access',
            'security_access',
            'driver_access',
            'survey_access',
            'time_and_attendance_access',
            'stock_access',
            'is_system_admin',
        ];

        $data = [];
        foreach ($syncableFields as $field) {
            $data[$field] = $user->$field;
        }

        return hash('sha256', json_encode($data));
    }

    /**
     * Check if user has local changes since last sync
     */
    public function hasLocalChanges(CustomerUser $user): bool
    {
        if (!$user->sync_hash) {
            return true; // Never synced
        }

        $currentHash = $this->generateSyncHash($user);
        return $currentHash !== $user->sync_hash;
    }

    /**
     * Handle conflict resolution using last-write-wins
     */
    public function handleConflict(CustomerUser $localUser, array $remoteUserData): CustomerUser
    {
        $remoteUpdatedAt = \Carbon\Carbon::parse($remoteUserData['updated_at']);
        $localUpdatedAt = $localUser->updated_at;

        if ($remoteUpdatedAt->gt($localUpdatedAt)) {
            // Remote is newer, update local
            $this->updateUserFromRemoteData($localUser, $remoteUserData);
            $this->logSync($localUser, 'inbound', 'conflict', 'Remote data was newer, local updated');
        } else {
            // Local is newer, keep local
            $this->logSync($localUser, 'inbound', 'conflict', 'Local data was newer, remote ignored');
        }

        return $localUser;
    }

    /**
     * Update user from remote data
     */
    protected function updateUserFromRemoteData(CustomerUser $user, array $remoteData): void
    {
        $user->skip_sync = true; // Prevent observer from triggering
        
        $user->update([
            'first_name' => $remoteData['first_name'],
            'last_name' => $remoteData['last_name'],
            'email_address' => $remoteData['email'],
            'cellphone' => $remoteData['cellphone'] ?? null,
            'console_access' => $remoteData['console_access'] ?? false,
            'firearm_access' => $remoteData['firearm_access'] ?? false,
            'responder_access' => $remoteData['responder_access'] ?? false,
            'reporter_access' => $remoteData['reporter_access'] ?? false,
            'security_access' => $remoteData['security_access'] ?? false,
            'driver_access' => $remoteData['driver_access'] ?? false,
            'survey_access' => $remoteData['survey_access'] ?? false,
            'time_and_attendance_access' => $remoteData['time_and_attendance_access'] ?? false,
            'stock_access' => $remoteData['stock_access'] ?? false,
            'is_system_admin' => $remoteData['is_system_admin'] ?? false,
        ]);
    }

    /**
     * Log sync operation
     */
    public function logSync(
        CustomerUser $user,
        string $direction,
        string $status,
        ?string $errorMessage = null,
        ?array $syncData = null
    ): void {
        UserSyncLog::create([
            'customer_user_id' => $user->id,
            'direction' => $direction,
            'status' => $status,
            'error_message' => $errorMessage,
            'sync_data' => $syncData,
            'synced_at' => Carbon::now(),
        ]);
    }

    /**
     * Check if user should be synced (cooldown period)
     */
    public function shouldSkipSync(CustomerUser $user): bool
    {
        if ($user->skip_sync) {
            return true;
        }

        // 30 second cooldown period
        if ($user->last_synced_at && $user->last_synced_at->gt(Carbon::now()->subSeconds(30))) {
            return true;
        }

        return false;
    }

    /**
     * Get users that need syncing
     */
    public function getUsersNeedingSync(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerUser::whereNotNull('super_admin_user_id')
            ->where(function ($query) {
                $query->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', Carbon::now()->subMinutes(5));
            })
            ->where('skip_sync', false)
            ->limit($limit)
            ->get();
    }

    /**
     * Check if user should be synced
     */
    protected function shouldSyncUser(CustomerUser $user): bool
    {
        // Must have super_admin_user_id
        if (!$user->super_admin_user_id) {
            return false;
        }

        // Skip if flag is set
        if ($user->skip_sync) {
            return false;
        }

        // Skip if in cooldown period
        if ($this->shouldSkipSync($user)) {
            return false;
        }

        // Must have local changes
        return $this->hasLocalChanges($user);
    }
}
