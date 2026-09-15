<?php

use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\UserSyncLog;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();

    // The rows this command exists to clean up predate the unique index, so the
    // only way to stage them is without it.
    Schema::table('customer_users', function (Blueprint $table) {
        $table->dropUnique('customer_users_customer_id_email_address_unique');
    });
});

function duplicateUser(int $customerId, string $email, array $attributes = []): CustomerUser
{
    $user = CustomerUser::factory()->create(array_merge(
        array_fill_keys(array_keys(UserSyncPayload::ACCESS_FLAGS), false),
        [
            'customer_id' => $customerId,
            'email_address' => $email,
            'cms_user_id' => null,
            'is_system_admin' => false,
            'skip_sync' => true,
        ],
    ));

    $user->timestamps = false;
    $user->forceFill($attributes)->saveQuietly();

    return $user->fresh();
}

function applyUniqueEmailIndex(): void
{
    Schema::table('customer_users', function (Blueprint $table) {
        $table->unique(['customer_id', 'email_address'], 'customer_users_customer_id_email_address_unique');
    });
}

function uniqueEmailMigration(): object
{
    return require database_path(
        'migrations/2026_09_15_104148_add_unique_email_per_customer_to_customer_users_table.php'
    );
}

function hasUniqueEmailIndex(): bool
{
    return collect(Schema::getIndexes('customer_users'))
        ->pluck('name')
        ->contains('customer_users_customer_id_email_address_unique');
}

it('reports duplicates without touching anything until --apply', function () {
    $customer = Customer::factory()->create();
    duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    duplicateUser($customer->id, 'shared@example.test');

    $this->artisan('app:merge-duplicate-customer-users')
        ->expectsOutputToContain('shared@example.test')
        ->assertSuccessful();

    expect(CustomerUser::withTrashed()->where('email_address', 'shared@example.test')->count())->toBe(2);
});

it('keeps the row a tenant is linked to and folds the others into it', function () {
    $customer = Customer::factory()->create();

    $linked = duplicateUser($customer->id, 'shared@example.test', [
        'cms_user_id' => 91,
        'first_name' => 'Richard',
        'last_name' => null,
        'cellphone' => null,
        'console_access' => true,
        'updated_at' => now()->subMonth(),
        'created_at' => now()->subYear(),
    ]);

    $newer = duplicateUser($customer->id, 'shared@example.test', [
        'first_name' => 'Rich',
        'last_name' => 'Roe',
        'cellphone' => '0821234567',
        'firearm_access' => true,
        'is_system_admin' => true,
        'updated_at' => now(),
        'created_at' => now()->subYears(2),
    ]);

    $log = UserSyncLog::create([
        'customer_user_id' => $newer->id,
        'direction' => 'outbound',
        'status' => 'success',
        'synced_at' => now(),
    ]);

    $this->artisan('app:merge-duplicate-customer-users', ['--apply' => true])->assertSuccessful();

    $survivor = CustomerUser::withTrashed()->find($linked->id);

    expect(CustomerUser::withTrashed()->find($newer->id))->toBeNull()
        ->and($survivor->console_access)->toBeTrue()
        ->and($survivor->firearm_access)->toBeTrue()
        ->and($survivor->is_system_admin)->toBeTrue()
        ->and($survivor->first_name)->toBe('Richard')
        ->and($survivor->last_name)->toBe('Roe')
        ->and($survivor->cellphone)->toBe('0821234567')
        ->and($survivor->cms_user_id)->toBe(91)
        ->and($survivor->created_at->toDateString())->toBe(now()->subYears(2)->toDateString())
        ->and($survivor->updated_at->toDateTimeString())->toBe($linked->updated_at->toDateTimeString())
        ->and($log->fresh()->customer_user_id)->toBe($linked->id);

    applyUniqueEmailIndex();
});

it('does not tell the tenant apps to archive the rows it removes', function () {
    $customer = Customer::factory()->create();
    duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    duplicateUser($customer->id, 'shared@example.test', ['skip_sync' => false]);

    Queue::fake();

    $this->artisan('app:merge-duplicate-customer-users', ['--apply' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

it('prefers a live row over a tombstoned one and removes the tombstone for good', function () {
    $customer = Customer::factory()->create();

    $tombstoned = duplicateUser($customer->id, 'shared@example.test', [
        'cms_user_id' => 91,
        'security_access' => true,
        'deleted_at' => now()->subDay(),
        'delete_scheduled' => now()->subDay(),
    ]);

    $live = duplicateUser($customer->id, 'shared@example.test');

    $this->artisan('app:merge-duplicate-customer-users', ['--apply' => true])->assertSuccessful();

    expect(CustomerUser::withTrashed()->find($tombstoned->id))->toBeNull()
        ->and(CustomerUser::withTrashed()->find($live->id)->security_access)->toBeTrue();

    applyUniqueEmailIndex();
});

it('leaves a group alone while two rows are linked to different tenant users', function () {
    $customer = Customer::factory()->create();

    $first = duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    $second = duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 92]);

    $this->artisan('app:merge-duplicate-customer-users', ['--apply' => true])
        ->expectsOutputToContain('human decision')
        ->assertFailed();

    expect(CustomerUser::withTrashed()->whereIn('id', [$first->id, $second->id])->count())->toBe(2);
});

it('merges an ambiguous group once a survivor is named with --keep', function () {
    $customer = Customer::factory()->create();

    $first = duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    $second = duplicateUser($customer->id, 'shared@example.test', [
        'cms_user_id' => 92,
        'driver_access' => true,
    ]);

    $this->artisan('app:merge-duplicate-customer-users', ['--keep' => $second->id, '--apply' => true])
        ->assertSuccessful();

    expect(CustomerUser::withTrashed()->find($first->id))->toBeNull()
        ->and(CustomerUser::withTrashed()->find($second->id)->cms_user_id)->toBe(92)
        ->and(CustomerUser::withTrashed()->find($second->id)->driver_access)->toBeTrue();

    applyUniqueEmailIndex();
});

it('only looks at the customer it was pointed at', function () {
    $mine = Customer::factory()->create();
    $theirs = Customer::factory()->create();

    duplicateUser($mine->id, 'shared@example.test');
    duplicateUser($mine->id, 'shared@example.test');
    $untouched = duplicateUser($theirs->id, 'other@example.test');
    duplicateUser($theirs->id, 'other@example.test');

    $this->artisan('app:merge-duplicate-customer-users', ['--customer' => $mine->id, '--apply' => true])
        ->assertSuccessful();

    expect(CustomerUser::withTrashed()->where('customer_id', $mine->id)->count())->toBe(1)
        ->and(CustomerUser::withTrashed()->where('customer_id', $theirs->id)->count())->toBe(2)
        ->and(CustomerUser::withTrashed()->find($untouched->id))->not->toBeNull();
});

it('says so when nothing needs merging', function () {
    $customer = Customer::factory()->create();
    duplicateUser($customer->id, 'unique@example.test');

    $this->artisan('app:merge-duplicate-customer-users')
        ->expectsOutputToContain('No customer users share an email')
        ->assertSuccessful();
});

it('collapses the duplicates it can decide when the migration runs', function () {
    $customer = Customer::factory()->create();

    $survivor = duplicateUser($customer->id, 'shared@example.test', [
        'last_synced_at' => now()->subHour(),
        'console_access' => true,
    ]);
    duplicateUser($customer->id, 'shared@example.test', ['firearm_access' => true]);
    duplicateUser($customer->id, 'shared@example.test', ['deleted_at' => now()]);
    duplicateUser($customer->id, 'kept@example.test');

    uniqueEmailMigration()->up();

    expect(hasUniqueEmailIndex())->toBeTrue()
        ->and(CustomerUser::withTrashed()->where('email_address', 'shared@example.test')->count())->toBe(1)
        ->and(CustomerUser::withTrashed()->find($survivor->id)->firearm_access)->toBeTrue();
});

it('stops the migration when two rows are linked to different tenant users', function () {
    $customer = Customer::factory()->create();

    duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 92]);
    duplicateUser($customer->id, 'other@example.test');
    duplicateUser($customer->id, 'other@example.test');

    expect(fn () => uniqueEmailMigration()->up())
        ->toThrow(RuntimeException::class, 'shared@example.test');

    // The groups it could decide are still collapsed, so only the real question is left.
    expect(hasUniqueEmailIndex())->toBeFalse()
        ->and(CustomerUser::withTrashed()->where('email_address', 'other@example.test')->count())->toBe(1)
        ->and(CustomerUser::withTrashed()->where('email_address', 'shared@example.test')->count())->toBe(2);
});

it('applies the index on the next run once the ambiguous group is decided', function () {
    $customer = Customer::factory()->create();

    duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 91]);
    $chosen = duplicateUser($customer->id, 'shared@example.test', ['cms_user_id' => 92]);

    expect(fn () => uniqueEmailMigration()->up())->toThrow(RuntimeException::class);

    $this->artisan('app:merge-duplicate-customer-users', ['--keep' => $chosen->id, '--apply' => true])
        ->assertSuccessful();

    uniqueEmailMigration()->up();

    expect(hasUniqueEmailIndex())->toBeTrue()
        ->and(CustomerUser::withTrashed()->where('email_address', 'shared@example.test')->count())->toBe(1);
});

it('refuses a --keep that is not part of a duplicate group', function () {
    $customer = Customer::factory()->create();
    $user = duplicateUser($customer->id, 'unique@example.test');

    $this->artisan('app:merge-duplicate-customer-users', ['--keep' => $user->id, '--apply' => true])
        ->assertFailed();

    $this->artisan('app:merge-duplicate-customer-users', ['--keep' => 987654, '--apply' => true])
        ->assertFailed();
});
