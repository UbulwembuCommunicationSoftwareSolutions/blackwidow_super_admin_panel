<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserToSuperAdminJob;
use App\Models\CustomerUser;
use App\Services\SuperAdminService;
use App\Services\UserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUsersWithSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-users-with-super-admin 
                            {--user-id= : Sync specific user by ID}
                            {--force : Force sync ignoring cooldown period}
                            {--batch-size=50 : Number of users to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bidirectional sync of users with SuperAdmin service';

    protected SuperAdminService $superAdminService;
    protected UserSyncService $userSyncService;

    public function __construct(SuperAdminService $superAdminService, UserSyncService $userSyncService)
    {
        parent::__construct();
        $this->superAdminService = $superAdminService;
        $this->userSyncService = $userSyncService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('services.superadmin.sync_enabled', true)) {
            $this->error('User sync is disabled. Set SUPERADMIN_SYNC_ENABLED=true in your .env file.');
            return 1;
        }

        $userId = $this->option('user-id');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');

        if ($userId) {
            return $this->syncSpecificUser($userId, $force);
        }

        return $this->syncAllUsers($batchSize, $force);
    }

    /**
     * Sync a specific user
     */
    protected function syncSpecificUser(int $userId, bool $force): int
    {
        $user = CustomerUser::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $this->info("Syncing user: {$user->first_name} {$user->last_name} ({$user->email_address})");

        try {
            // Step 1: Import from SuperAdmin (inbound)
            $this->info('Step 1: Importing from SuperAdmin...');
            $importResult = $this->superAdminService->importUsers();
            $this->info("Imported: {$importResult['imported']}, Updated: {$importResult['updated']}");

            // Step 2: Sync to SuperAdmin (outbound)
            $this->info('Step 2: Syncing to SuperAdmin...');
            $success = $this->userSyncService->syncUserToSuperAdmin($user);
            
            if ($success) {
                $this->info('✅ User sync completed successfully');
                return 0;
            } else {
                $this->error('❌ User sync failed');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            Log::error('Manual user sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Sync all users
     */
    protected function syncAllUsers(int $batchSize, bool $force): int
    {
        $this->info('Starting bidirectional user sync with SuperAdmin...');
        $this->info("Batch size: {$batchSize}");

        try {
            // Step 1: Import all users from SuperAdmin (inbound)
            $this->info('Step 1: Importing users from SuperAdmin...');
            $importResult = $this->superAdminService->importUsers();
            $this->info("✅ Import completed - Imported: {$importResult['imported']}, Updated: {$importResult['updated']}, Total: {$importResult['total']}");

            // Step 2: Find users that need syncing to SuperAdmin (outbound)
            $this->info('Step 2: Finding users that need syncing to SuperAdmin...');
            $usersNeedingSync = $this->userSyncService->getUsersNeedingSync($batchSize);
            $totalUsers = $usersNeedingSync->count();

            if ($totalUsers === 0) {
                $this->info('✅ No users need syncing to SuperAdmin');
                return 0;
            }

            $this->info("Found {$totalUsers} users that need syncing");

            // Step 3: Process users in batches
            $progressBar = $this->output->createProgressBar($totalUsers);
            $progressBar->start();

            $successCount = 0;
            $failureCount = 0;

            foreach ($usersNeedingSync as $user) {
                try {
                    $success = $this->userSyncService->syncUserToSuperAdmin($user);
                    if ($success) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::error('Failed to sync user in batch', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Summary
            $this->info("✅ Sync completed successfully");
            $this->info("📊 Summary:");
            $this->info("   - Users synced successfully: {$successCount}");
            $this->info("   - Users failed to sync: {$failureCount}");
            $this->info("   - Total processed: {$totalUsers}");

            return $failureCount > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            Log::error('Bidirectional user sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
