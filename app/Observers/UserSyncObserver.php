<?php

namespace App\Observers;

use App\Jobs\SyncUserToSuperAdminJob;
use App\Models\CustomerUser;
use App\Services\UserSyncService;
use Illuminate\Support\Facades\Log;

class UserSyncObserver
{
    protected UserSyncService $userSyncService;

    public function __construct(UserSyncService $userSyncService)
    {
        $this->userSyncService = $userSyncService;
    }

    /**
     * Handle the CustomerUser "created" event.
     */
    public function created(CustomerUser $customerUser): void
    {
        $this->triggerSyncIfNeeded($customerUser, 'created');
    }

    /**
     * Handle the CustomerUser "updated" event.
     */
    public function updated(CustomerUser $customerUser): void
    {
        $this->triggerSyncIfNeeded($customerUser, 'updated');
    }

    /**
     * Handle the CustomerUser "deleted" event.
     */
    public function deleted(CustomerUser $customerUser): void
    {
        // Note: We don't sync deletions to SuperAdmin as per the spec
        // The user might be restored later
    }

    /**
     * Handle the CustomerUser "restored" event.
     */
    public function restored(CustomerUser $customerUser): void
    {
        $this->triggerSyncIfNeeded($customerUser, 'restored');
    }

    /**
     * Handle the CustomerUser "force deleted" event.
     */
    public function forceDeleted(CustomerUser $customerUser): void
    {
        // Note: We don't sync force deletions to SuperAdmin
    }

    /**
     * Trigger sync if conditions are met
     */
    protected function triggerSyncIfNeeded(CustomerUser $customerUser, string $event): void
    {
        // Skip if sync is disabled
        if (!config('services.superadmin.sync_enabled', true)) {
            return;
        }

        // Skip if user has skip_sync flag
        if ($customerUser->skip_sync) {
            return;
        }

        // Skip if user doesn't have super_admin_user_id (not yet synced)
        if (!$customerUser->super_admin_user_id) {
            return;
        }

        // Skip if in cooldown period
        if ($this->userSyncService->shouldSkipSync($customerUser)) {
            return;
        }

        // Check if user has local changes
        if (!$this->userSyncService->hasLocalChanges($customerUser)) {
            return;
        }

        // Dispatch sync job
        try {
            SyncUserToSuperAdminJob::dispatch($customerUser);
            
            Log::info('User sync job dispatched', [
                'user_id' => $customerUser->id,
                'event' => $event,
                'super_admin_user_id' => $customerUser->super_admin_user_id
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user sync job', [
                'user_id' => $customerUser->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }
}
