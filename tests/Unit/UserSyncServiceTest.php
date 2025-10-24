<?php

use App\Models\CustomerUser;
use App\Services\SuperAdminService;
use App\Services\UserSyncService;
use Carbon\Carbon;
use Mockery;

beforeEach(function () {
    $this->superAdminService = Mockery::mock(SuperAdminService::class);
    $this->userSyncService = new UserSyncService($this->superAdminService);
});

afterEach(function () {
    Mockery::close();
});

test('generates consistent sync hash', function () {
    $user = new CustomerUser([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email_address' => 'john@example.com',
        'cellphone' => '1234567890',
        'console_access' => true,
        'firearm_access' => false,
    ]);

    $hash1 = $this->userSyncService->generateSyncHash($user);
    $hash2 = $this->userSyncService->generateSyncHash($user);

    expect($hash1)->toBe($hash2);
    expect($hash1)->toBeString();
    expect(strlen($hash1))->toBe(64); // SHA256 length
});

test('detects local changes correctly', function () {
    $user = new CustomerUser([
        'sync_hash' => 'old_hash'
    ]);

    // Should detect changes when hash is different
    expect($this->userSyncService->hasLocalChanges($user))->toBeTrue();

    // Update hash to match current state
    $user->sync_hash = $this->userSyncService->generateSyncHash($user);

    // Should not detect changes when hash matches
    expect($this->userSyncService->hasLocalChanges($user))->toBeFalse();
});

test('skips sync when flag is set', function () {
    $user = new CustomerUser([
        'skip_sync' => true
    ]);

    expect($this->userSyncService->shouldSkipSync($user))->toBeTrue();
});

test('does not skip sync when flag is false and no last sync', function () {
    $user = new CustomerUser([
        'skip_sync' => false,
        'last_synced_at' => null
    ]);

    expect($this->userSyncService->shouldSkipSync($user))->toBeFalse();
});

test('sync hash includes all syncable fields', function () {
    $user1 = new CustomerUser([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email_address' => 'john@example.com',
        'console_access' => true,
        'firearm_access' => false,
    ]);

    $user2 = new CustomerUser([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email_address' => 'john@example.com',
        'console_access' => true,
        'firearm_access' => true, // Different value
    ]);

    $hash1 = $this->userSyncService->generateSyncHash($user1);
    $hash2 = $this->userSyncService->generateSyncHash($user2);

    expect($hash1)->not->toBe($hash2);
});
