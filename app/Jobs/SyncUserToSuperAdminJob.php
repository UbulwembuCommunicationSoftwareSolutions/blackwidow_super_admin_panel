<?php

namespace App\Jobs;

use App\Models\CustomerUser;
use App\Services\UserSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUserToSuperAdminJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public CustomerUser $customerUser;
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(CustomerUser $customerUser)
    {
        $this->customerUser = $customerUser;
    }

    /**
     * Execute the job.
     */
    public function handle(UserSyncService $userSyncService): void
    {
        try {
            Log::info('Starting user sync job', [
                'user_id' => $this->customerUser->id,
                'super_admin_user_id' => $this->customerUser->super_admin_user_id,
                'attempt' => $this->attempts()
            ]);

            $success = $userSyncService->syncUserToSuperAdmin($this->customerUser);

            if ($success) {
                Log::info('User sync job completed successfully', [
                    'user_id' => $this->customerUser->id,
                    'super_admin_user_id' => $this->customerUser->super_admin_user_id
                ]);
            } else {
                Log::warning('User sync job completed but sync failed', [
                    'user_id' => $this->customerUser->id,
                    'super_admin_user_id' => $this->customerUser->super_admin_user_id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('User sync job failed with exception', [
                'user_id' => $this->customerUser->id,
                'super_admin_user_id' => $this->customerUser->super_admin_user_id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('User sync job failed permanently', [
            'user_id' => $this->customerUser->id,
            'super_admin_user_id' => $this->customerUser->super_admin_user_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        // Log the final failure
        $userSyncService = app(UserSyncService::class);
        $userSyncService->logSync(
            $this->customerUser,
            'outbound',
            'failed',
            'Job failed after ' . $this->attempts() . ' attempts: ' . $exception->getMessage()
        );
    }
}
