<?php

use App\Jobs\PushStaffUserToLmsJob;
use App\Models\User;
use App\Services\Lms\LmsStaffPusher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.lms.hub_url' => 'https://lms-hub.example.test',
        'services.lms.sync_token' => 'lms-hub-token',
        'customer_sync.enabled' => false,
        'user_sync.enabled' => true,
    ]);
});

it('queues a push when a staff user is created, changed or deleted', function () {
    Queue::fake();

    $user = User::factory()->create();
    Queue::assertPushed(PushStaffUserToLmsJob::class, fn ($job) => $job->staffUserId === $user->id && ! $job->archive);

    Queue::fake();
    $user->update(['remember_token' => 'abc']);
    Queue::assertNothingPushed();

    $user->update(['email' => 'renamed@example.test']);
    Queue::assertPushed(PushStaffUserToLmsJob::class, 1);

    Queue::fake();
    $user->delete();
    Queue::assertPushed(PushStaffUserToLmsJob::class, fn ($job) => $job->staffUserId === $user->id && $job->archive);
});

it('does not queue pushes when the LMS is not configured', function () {
    Queue::fake();
    config(['services.lms.hub_url' => null]);

    User::factory()->create();

    Queue::assertNothingPushed();
});

it('sends the staff profile and password hash, never the password', function () {
    Queue::fake();
    Http::fake(['https://lms-hub.example.test/*' => Http::response(['success' => true, 'outcome' => 'created', 'user' => ['id' => 5]], 201)]);

    $user = User::factory()->create(['name' => 'Ops Person', 'email' => 'ops@example.test', 'password' => 'secret-pass']);

    (new PushStaffUserToLmsJob($user->id))->handle(app(LmsStaffPusher::class));

    Http::assertSent(function (Request $request) use ($user) {
        return $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/staff'
            && $request->hasHeader('Authorization', 'Bearer lms-hub-token')
            && $request['super_admin_staff_id'] === $user->id
            && $request['name'] === 'Ops Person'
            && $request['email'] === 'ops@example.test'
            && Hash::check('secret-pass', $request['password_hash'])
            && ! str_contains($request->body(), 'secret-pass');
    });
});

it('archives a deleted staff user and tolerates one the LMS never had', function () {
    Http::fake(['https://lms-hub.example.test/*' => Http::response(['success' => false], 404)]);

    (new PushStaffUserToLmsJob(77, archive: true))->handle(app(LmsStaffPusher::class));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://lms-hub.example.test/admin-api/v1/sync/staff/archive'
        && $request['super_admin_staff_id'] === 77);
});

it('throws so the job retries when the LMS rejects a push', function () {
    Queue::fake();
    Http::fake(['https://lms-hub.example.test/*' => Http::response(['message' => 'conflict'], 409)]);

    $user = User::factory()->create();

    (new PushStaffUserToLmsJob($user->id))->handle(app(LmsStaffPusher::class));
})->throws(RuntimeException::class);

it('pushes every staff user from the command', function () {
    Queue::fake();
    Http::fake(['https://lms-hub.example.test/*' => Http::response(['success' => true, 'outcome' => 'updated', 'user' => ['id' => 9]], 200)]);

    User::factory()->count(2)->create();

    $this->artisan('lms:push-staff', ['--dry-run' => true])->assertSuccessful();
    Http::assertNothingSent();

    $this->artisan('lms:push-staff')->assertSuccessful();
    Http::assertSentCount(2);
});
