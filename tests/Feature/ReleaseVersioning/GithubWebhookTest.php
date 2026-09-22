<?php

use App\Jobs\SyncGithubReleasesJob;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('rejects github webhooks with invalid signatures', function () {
    config(['services.github.webhook_secret' => 'super-secret']);

    $this->postJson('/api/webhooks/github/releases', ['zen' => 'hello'], [
        'X-Hub-Signature-256' => 'sha256=deadbeef',
        'X-GitHub-Event' => 'ping',
    ])->assertUnauthorized();
});

it('accepts a signed ping', function () {
    config(['services.github.webhook_secret' => 'super-secret']);
    $payload = json_encode(['zen' => 'hello'], JSON_THROW_ON_ERROR);
    $sig = 'sha256='.hash_hmac('sha256', $payload, 'super-secret');

    $this->call(
        'POST',
        '/api/webhooks/github/releases',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
            'HTTP_X_GITHUB_EVENT' => 'ping',
        ],
        $payload
    )->assertOk()->assertJson(['pong' => true]);
});

it('dispatches sync jobs for matching subscription types on release published', function () {
    config(['services.github.webhook_secret' => 'super-secret']);
    Queue::fake();

    $typeA = SubscriptionType::factory()->create(['github_repo' => 'acme/console']);
    $typeB = SubscriptionType::factory()->create(['github_repo' => 'https://github.com/acme/console']);
    SubscriptionType::factory()->create(['github_repo' => 'acme/other']);

    $payload = json_encode([
        'action' => 'published',
        'repository' => ['full_name' => 'acme/console'],
    ], JSON_THROW_ON_ERROR);
    $sig = 'sha256='.hash_hmac('sha256', $payload, 'super-secret');

    $this->call(
        'POST',
        '/api/webhooks/github/releases',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $sig,
            'HTTP_X_GITHUB_EVENT' => 'release',
        ],
        $payload
    )->assertOk()->assertJson(['synced_types' => 2]);

    Queue::assertPushed(SyncGithubReleasesJob::class, 2);
    Queue::assertPushed(SyncGithubReleasesJob::class, fn (SyncGithubReleasesJob $job) => $job->subscriptionTypeId === $typeA->id);
    Queue::assertPushed(SyncGithubReleasesJob::class, fn (SyncGithubReleasesJob $job) => $job->subscriptionTypeId === $typeB->id);
});
