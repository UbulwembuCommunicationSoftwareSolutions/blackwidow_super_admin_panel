<?php

use App\Jobs\SendWelcomeEmailJob;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

const GENERIC_RESET_MESSAGE = 'If an account matches that email address, a password reset link has been sent.';

beforeEach(function () {
    Notification::fake();
    Queue::fake();
});

function resetLinkFor(User $user): string
{
    $url = null;

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$url) {
        $url = $notification->resetUrl;

        return true;
    });

    return $url;
}

it('validates the forgot password email', function (array $payload, array $errors) {
    $this->postJson('/api/backend/forgot-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing' => [[], ['email']],
    'not an email' => [['email' => 'nope'], ['email']],
    'unknown portal' => [['email' => 'a@example.com', 'portal' => 'staff'], ['portal']],
]);

it('emails a staff user a reset link pointing at the requesting spa origin', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->withHeaders(['Origin' => 'https://superadmin.aims.net.za'])
        ->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk()
        ->assertJsonPath('message', GENERIC_RESET_MESSAGE);

    $url = resetLinkFor($user);

    expect($url)->toStartWith('https://superadmin.aims.net.za/reset-password?');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['email'])->toBe('admin@example.com')
        ->and($query['token'])->toBeString()->not->toBeEmpty()
        ->and(Password::broker()->tokenExists($user, $query['token']))->toBeTrue();

    Queue::assertNothingPushed();
});

it('falls back to the configured frontend url for an unknown origin', function () {
    config()->set('frontend.url', 'https://superadmin.blackwidow.org.za');
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->withHeaders(['Origin' => 'https://evil.example.com'])
        ->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk();

    expect(resetLinkFor($user))->toStartWith('https://superadmin.blackwidow.org.za/reset-password?');
});

it('uses the configured frontend url when no origin is sent', function () {
    config()->set('frontend.url', 'https://superadmin.siyaleader.org.za');
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk();

    expect(resetLinkFor($user))->toStartWith('https://superadmin.siyaleader.org.za/reset-password?');
});

it('renders the reset email with the link and expiry', function () {
    $user = User::factory()->create();
    $notification = new ResetPasswordNotification('https://superadmin.blackwidow.org.za/reset-password?token=abc&email=x', 60);

    $mail = $notification->toMail($user);

    expect($mail->actionUrl)->toBe('https://superadmin.blackwidow.org.za/reset-password?token=abc&email=x')
        ->and($mail->actionText)->toBe('Reset password')
        ->and($mail->subject)->toContain('Reset your')
        ->and(implode(' ', $mail->outroLines))->toContain('60 minutes');
});

it('still responds generically and reports when the reset email cannot be sent', function () {
    $this->mock(Dispatcher::class)
        ->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP down'));
    $reported = null;
    app(ExceptionHandler::class)->reportable(function (RuntimeException $e) use (&$reported) {
        $reported = $e;

        return false;
    });
    User::factory()->create(['email' => 'admin@example.com']);

    $this->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk()
        ->assertJsonPath('message', GENERIC_RESET_MESSAGE);

    expect($reported)->toBeInstanceOf(RuntimeException::class)
        ->and($reported->getMessage())->toBe('SMTP down');
});

it('responds identically for an unknown email without sending anything', function () {
    $this->postJson('/api/backend/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('message', GENERIC_RESET_MESSAGE);

    Notification::assertNothingSent();
    Queue::assertNothingPushed();
});

it('asks the tenant console to email a customer admin instead of sending a hub link', function () {
    $customer = Customer::factory()->create();
    $admin = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'portal@example.com',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/forgot-password', ['email' => 'portal@example.com'])
        ->assertOk()
        ->assertJsonPath('message', GENERIC_RESET_MESSAGE);

    Queue::assertPushed(SendWelcomeEmailJob::class, fn (SendWelcomeEmailJob $job) => $job->customerUser->is($admin));
    Notification::assertNothingSent();
});

it('does not email a customer user who is not a system admin', function () {
    $customer = Customer::factory()->create();
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'member@example.com',
        'is_system_admin' => false,
        'skip_sync' => true,
    ]);
    Queue::fake();

    $this->postJson('/api/backend/forgot-password', ['email' => 'member@example.com'])
        ->assertOk();

    Queue::assertNothingPushed();
    Notification::assertNothingSent();
});

it('does not email a trashed customer admin', function () {
    $customer = Customer::factory()->create();
    $admin = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'gone@example.com',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);
    $admin->delete();
    Queue::fake();

    $this->postJson('/api/backend/forgot-password', ['email' => 'gone@example.com'])
        ->assertOk();

    Queue::assertNothingPushed();
});

it('emails every console when a customer admin address is shared by two companies', function () {
    foreach (Customer::factory()->count(2)->create() as $customer) {
        CustomerUser::factory()->create([
            'customer_id' => $customer->id,
            'email_address' => 'shared@example.com',
            'is_system_admin' => true,
            'skip_sync' => true,
        ]);
    }
    Queue::fake();

    $this->postJson('/api/backend/forgot-password', ['email' => 'shared@example.com'])
        ->assertOk();

    Queue::assertPushed(SendWelcomeEmailJob::class, 2);
});

it('prefers the staff user when the email is shared with a customer admin', function () {
    $customer = Customer::factory()->create();
    $staff = User::factory()->create(['email' => 'shared@example.com']);
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'shared@example.com',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);
    Queue::fake();

    $this->postJson('/api/backend/forgot-password', ['email' => 'shared@example.com'])
        ->assertOk();

    Notification::assertSentTo($staff, ResetPasswordNotification::class);
    Queue::assertNothingPushed();
});

it('targets the customer admin on the customer portal when a staff user shares the email', function () {
    $customer = Customer::factory()->create();
    $staff = User::factory()->create(['email' => 'shared@example.com']);
    $admin = CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'shared@example.com',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/forgot-password', ['email' => 'shared@example.com', 'portal' => 'customer'])
        ->assertOk();

    Queue::assertPushed(SendWelcomeEmailJob::class, fn (SendWelcomeEmailJob $job) => $job->customerUser->is($admin));
    Notification::assertNotSentTo($staff, ResetPasswordNotification::class);
});

it('does not re-send a staff reset link within the broker throttle window', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $this->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])->assertOk();
    $this->postJson('/api/backend/forgot-password', ['email' => 'admin@example.com'])
        ->assertOk()
        ->assertJsonPath('message', GENERIC_RESET_MESSAGE);

    Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
});

it('rate limits forgot password requests', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/backend/forgot-password', ['email' => "user{$i}@example.com"])->assertOk();
    }

    $this->postJson('/api/backend/forgot-password', ['email' => 'user6@example.com'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('validates the reset password payload', function (array $payload, array $errors) {
    $this->postJson('/api/backend/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'empty' => [[], ['token', 'email', 'password']],
    'unconfirmed password' => [[
        'token' => 'abc',
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'different',
    ], ['password']],
    'short password' => [[
        'token' => 'abc',
        'email' => 'admin@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ], ['password']],
]);

it('resets a staff password with a valid token, revokes tokens and lets the user sign in', function () {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'old-password',
    ]);
    $user->createToken('backend', ['backend']);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/backend/reset-password', [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('message', 'Your password has been reset. You can now sign in.');

    $user->refresh();

    expect(Hash::check('new-password-123', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse()
        ->and($user->tokens()->count())->toBe(0)
        ->and(Password::broker()->tokenExists($user, $token))->toBeFalse();

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($user));

    $this->postJson('/api/backend/login', [
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
    ])->assertOk();

    $this->postJson('/api/backend/login', [
        'email' => 'admin@example.com',
        'password' => 'old-password',
    ])->assertUnprocessable();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'old-password',
    ]);
    Password::broker()->createToken($user);

    $this->postJson('/api/backend/reset-password', [
        'token' => 'not-the-token',
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});

it('rejects a reset token that has expired', function () {
    config()->set('auth.passwords.users.expire', 60);
    $user = User::factory()->create(['email' => 'admin@example.com']);
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->postJson('/api/backend/reset-password', [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects a reset for an email that has no staff account', function () {
    $this->postJson('/api/backend/reset-password', [
        'token' => 'whatever',
        'email' => 'nobody@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('cannot reset a customer admin through the staff broker', function () {
    $customer = Customer::factory()->create();
    CustomerUser::factory()->create([
        'customer_id' => $customer->id,
        'email_address' => 'portal@example.com',
        'password' => 'old-password',
        'is_system_admin' => true,
        'skip_sync' => true,
    ]);

    $this->postJson('/api/backend/reset-password', [
        'token' => 'whatever',
        'email' => 'portal@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('cannot reuse a reset token', function () {
    $user = User::factory()->create(['email' => 'admin@example.com']);
    $token = Password::broker()->createToken($user);
    $payload = [
        'token' => $token,
        'email' => 'admin@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ];

    $this->postJson('/api/backend/reset-password', $payload)->assertOk();
    $this->postJson('/api/backend/reset-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
