<?php

use App\Models\CustomerUser;
use App\Support\UserSync\TenantResolver;
use App\Support\UserSync\UserSyncPayload;

/*
 * Unit coverage for the canonical sync contract itself: the payload both systems
 * speak, and the tenant resolution that decides which customer a request belongs
 * to. The stateful apply logic is covered in tests/Feature/UserSyncTest.php and
 * tests/Feature/CanonicalUserSyncTest.php.
 */

it('round trips a customer user through the canonical payload', function () {
    $user = new CustomerUser([
        'cms_user_id' => 42,
        'email_address' => 'round@trip.test',
        'first_name' => 'Round',
        'last_name' => 'Trip',
        'cellphone' => '+27123456789',
        'console_access' => true,
        'firearm_access' => true,
        'is_system_admin' => true,
    ]);
    $user->id = 7;

    $payload = UserSyncPayload::fromCustomerUser($user);
    $restored = UserSyncPayload::fromArray($payload->toArray());

    expect($restored->superAdminUserId)->toBe(7)
        ->and($restored->cmsUserId)->toBe(42)
        ->and($restored->email)->toBe('round@trip.test')
        ->and($restored->firstName)->toBe('Round')
        ->and($restored->cellphone)->toBe('+27123456789')
        ->and($restored->isSystemAdmin)->toBeTrue()
        ->and($restored->accessFlag('console_access'))->toBeTrue()
        ->and($restored->accessFlag('firearm_access'))->toBeTrue()
        ->and($restored->accessFlag('responder_access'))->toBeFalse();
});

it('reads access flags sent as integers or strings', function (mixed $value, bool $expected) {
    $payload = UserSyncPayload::fromArray([
        'email' => 'flags@test.test',
        'console_access' => $value,
    ]);

    expect($payload->accessFlag('console_access'))->toBe($expected);
})->with([
    'integer one' => [1, true],
    'integer zero' => [0, false],
    'string one' => ['1', true],
    'string zero' => ['0', false],
    'boolean true' => [true, true],
    'string true' => ['true', true],
    'string false' => ['false', false],
]);

it('treats a missing access flag as no access', function () {
    $payload = UserSyncPayload::fromArray(['email' => 'sparse@test.test']);

    foreach (array_keys(UserSyncPayload::ACCESS_FLAGS) as $flag) {
        expect($payload->accessFlag($flag))->toBeFalse();
    }
});

it('never exposes the password in the canonical payload', function () {
    $user = new CustomerUser(['email_address' => 'secret@test.test']);
    $user->setRawAttributes(['password' => 'already-hashed-value'], sync: false);

    expect(UserSyncPayload::fromCustomerUser($user)->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('password_hash');
});

it('maps subscription types to the access flag that gates them', function () {
    expect(UserSyncPayload::flagForSubscriptionType(1))->toBe('console_access')
        ->and(UserSyncPayload::flagForSubscriptionType(2))->toBe('firearm_access')
        ->and(UserSyncPayload::flagForSubscriptionType(9))->toBe('time_and_attendance_access')
        ->and(UserSyncPayload::flagForSubscriptionType(99))->toBeNull();
});

it('normalises app urls so scheme, case and trailing slash do not matter', function (string $input) {
    expect(TenantResolver::normalise($input))->toBe('cms.example.test');
})->with([
    'https' => ['https://cms.example.test'],
    'http' => ['http://cms.example.test'],
    'trailing slash' => ['https://cms.example.test/'],
    'mixed case' => ['https://CMS.Example.Test'],
    'padded' => ['  https://cms.example.test  '],
    'bare host' => ['cms.example.test'],
]);

it('normalises empty app urls to an empty string', function () {
    expect(TenantResolver::normalise(null))->toBe('')
        ->and(TenantResolver::normalise(''))->toBe('');
});
