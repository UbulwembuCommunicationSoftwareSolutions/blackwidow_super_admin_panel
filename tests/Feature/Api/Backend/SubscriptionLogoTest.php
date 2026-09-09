<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::fake();
    Queue::fake();
    Storage::fake('public');
});

function logoSubscription(): CustomerSubscription
{
    return CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'customer_id' => Customer::factory()->create()->id,
    ]);
}

/**
 * Multipart requests need an explicit Accept header, otherwise a failed
 * validation redirects instead of returning the 422 body.
 *
 * @var array<string, string>
 */
const LOGO_HEADERS = ['Accept' => 'application/json'];

it('rejects logo uploads without a token', function () {
    $row = logoSubscription();

    $this->postJson("/api/backend/customer-subscriptions/{$row->id}/logos")
        ->assertUnauthorized();
});

it('forbids logo uploads without Shield permissions', function () {
    actingAsBackendForbidden();
    $row = logoSubscription();

    $this->postJson("/api/backend/customer-subscriptions/{$row->id}/logos")
        ->assertForbidden();
});

it('requires at least one file or slot to clear', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $this->postJson("/api/backend/customer-subscriptions/{$row->id}/logos", [])
        ->assertStatus(422);
});

it('stores an uploaded logo and returns its public url', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $response = $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_1' => UploadedFile::fake()->image('login.png'),
    ])->assertOk();

    $path = $row->fresh()->logo_1;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
    expect($response->json('data.logo_urls.logo_1'))->toContain($path);
});

it('replaces an existing logo and deletes the old file', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_2' => UploadedFile::fake()->image('first.png'),
    ])->assertOk();
    $first = $row->fresh()->logo_2;

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_2' => UploadedFile::fake()->image('second.png'),
    ])->assertOk();
    $second = $row->fresh()->logo_2;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);
});

it('clears a slot and removes the file from disk', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_3' => UploadedFile::fake()->image('bg.png'),
    ])->assertOk();
    $path = $row->fresh()->logo_3;

    $response = $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'clear' => ['logo_3'],
    ])->assertOk();

    expect($row->fresh()->logo_3)->toBeNull();
    expect($response->json('data.logo_urls'))->not->toHaveKey('logo_3');
    Storage::disk('public')->assertMissing($path);
});

it('rejects uploading and clearing the same slot', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_1' => UploadedFile::fake()->image('login.png'),
        'clear' => ['logo_1'],
    ], LOGO_HEADERS)->assertStatus(422)->assertJsonValidationErrors(['clear']);

    expect($row->fresh()->logo_1)->toBe($row->logo_1);
});

it('rejects unknown slots and non-image files', function () {
    actingAsBackendUser();
    $row = logoSubscription();

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'clear' => ['logo_9'],
    ], LOGO_HEADERS)->assertStatus(422)->assertJsonValidationErrors(['clear.0']);

    $this->post("/api/backend/customer-subscriptions/{$row->id}/logos", [
        'logo_1' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ], LOGO_HEADERS)->assertStatus(422)->assertJsonValidationErrors(['logo_1']);
});

it('exposes logo slot labels on the subscription type endpoint', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();

    $labels = $this->getJson("/api/backend/subscription-types/{$type->id}")
        ->assertOk()
        ->json('data.logo_descriptions');

    expect($labels)->toHaveCount(5);
    // The last two slots are unused for every product, so they come back null
    // and the client can skip rendering them.
    expect(array_slice($labels, 3))->toBe([null, null]);
    expect(array_slice($labels, 0, 3))->not->toContain(null);
});

it('labels the mobile app slots differently to the web products', function () {
    $web = new SubscriptionType(['name' => 'Web']);
    $web->id = 1;

    $app = new SubscriptionType(['name' => 'App']);
    $app->id = 3;

    expect($web->logo_descriptions)->toBe(['Login Logo', 'Menu Logo', 'Login Background', null, null]);
    expect($app->logo_descriptions)->toBe(['App Logo', 'Home Logo', 'Login Logo', null, null]);
});
