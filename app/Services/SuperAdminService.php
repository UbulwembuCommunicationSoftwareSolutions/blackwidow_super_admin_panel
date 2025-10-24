<?php

namespace App\Services;

use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class SuperAdminService
{
    protected string $apiUrl;
    protected ?string $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('services.superadmin.api_url', 'https://superadmin.blackwidow.org.za');
        $this->apiToken = config('services.superadmin.api_token');
    }

    /**
     * Import users from SuperAdmin
     */
    public function importUsers(): array
    {
        $response = Http::withToken($this->apiToken)
            ->post($this->apiUrl . '/api/user-import');

        if (!$response->successful()) {
            Log::error('Failed to import users from SuperAdmin', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        }

        $data = $response->json();
        $importedCount = 0;
        $updatedCount = 0;

        foreach ($data['users'] ?? [] as $userData) {
            $result = $this->syncUserBidirectional($userData);
            if ($result['action'] === 'created') {
                $importedCount++;
            } elseif ($result['action'] === 'updated') {
                $updatedCount++;
            }
        }

        return [
            'imported' => $importedCount,
            'updated' => $updatedCount,
            'total' => count($data['users'] ?? [])
        ];
    }

    /**
     * Sync a single user bidirectionally with conflict resolution
     */
    public function syncUserBidirectional(array $remoteUserData): array
    {
        $localUser = CustomerUser::where('super_admin_user_id', $remoteUserData['id'])->first();

        if (!$localUser) {
            // Create new user
            $localUser = $this->createUserFromRemoteData($remoteUserData);
            return ['action' => 'created', 'user' => $localUser];
        }

        // Check for conflicts using last-write-wins
        $remoteUpdatedAt = \Carbon\Carbon::parse($remoteUserData['updated_at']);
        $localUpdatedAt = $localUser->updated_at;

        if ($remoteUpdatedAt->gt($localUpdatedAt)) {
            // Remote is newer, update local
            $this->updateUserFromRemoteData($localUser, $remoteUserData);
            return ['action' => 'updated', 'user' => $localUser];
        } elseif ($localUpdatedAt->gt($remoteUpdatedAt)) {
            // Local is newer, will be pushed later
            return ['action' => 'skipped', 'user' => $localUser];
        }

        // Same timestamp, no conflict
        return ['action' => 'no_change', 'user' => $localUser];
    }

    /**
     * Create a new user from remote data
     */
    protected function createUserFromRemoteData(array $remoteData): CustomerUser
    {
        $user = CustomerUser::create([
            'super_admin_user_id' => $remoteData['id'],
            'customer_id' => $remoteData['customer_id'] ?? 1, // Default customer
            'first_name' => $remoteData['first_name'],
            'last_name' => $remoteData['last_name'],
            'email_address' => $remoteData['email'],
            'password' => Hash::make('temporary_password_' . uniqid()),
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
            'skip_sync' => true, // Prevent observer from triggering
        ]);

        // Log the import
        UserSyncLog::create([
            'customer_user_id' => $user->id,
            'direction' => 'inbound',
            'status' => 'success',
            'sync_data' => $remoteData,
            'synced_at' => now(),
        ]);

        return $user;
    }

    /**
     * Update local user from remote data
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

        // Log the update
        UserSyncLog::create([
            'customer_user_id' => $user->id,
            'direction' => 'inbound',
            'status' => 'success',
            'sync_data' => $remoteData,
            'synced_at' => now(),
        ]);
    }

    /**
     * Update user on SuperAdmin
     */
    public function updateUser(CustomerUser $user): array
    {
        $userData = [
            'super_admin_user_id' => $user->super_admin_user_id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email_address,
            'cellphone' => $user->cellphone,
            'console_access' => $user->console_access,
            'firearm_access' => $user->firearm_access,
            'responder_access' => $user->responder_access,
            'reporter_access' => $user->reporter_access,
            'security_access' => $user->security_access,
            'driver_access' => $user->driver_access,
            'survey_access' => $user->survey_access,
            'time_and_attendance_access' => $user->time_and_attendance_access,
            'stock_access' => $user->stock_access,
            'is_system_admin' => $user->is_system_admin,
            'updated_at' => $user->updated_at->toISOString(),
        ];

        $response = Http::withToken($this->apiToken)
            ->post($this->apiUrl . '/api/update-user', $userData);

        if (!$response->successful()) {
            throw new \Exception('Failed to update user on SuperAdmin: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch a single user from SuperAdmin
     */
    public function fetchUser(string $superAdminUserId): ?array
    {
        $response = Http::withToken($this->apiToken)
            ->get($this->apiUrl . '/api/user/' . $superAdminUserId);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}
