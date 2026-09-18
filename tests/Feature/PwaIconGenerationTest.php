<?php

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

function pwaSubscription(): CustomerSubscription
{
    $path = UploadedFile::fake()->image('logo.png', 400, 200)->store('logos', 'public');

    return CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
        'url' => 'https://reporter.example.test',
        'app_name' => 'Reporter',
        'logo_1' => $path,
    ]);
}

/**
 * @return array<string, array{src: string, sizes: string, type: string, purpose: string}>
 */
function manifestIconsByFilename(array $icons): array
{
    $byFilename = [];

    foreach ($icons as $icon) {
        $byFilename[basename((string) parse_url($icon['src'], PHP_URL_PATH))] = $icon;
    }

    return $byFilename;
}

it('generates pwa icons from logo_1 via generate-logos and serves them in the manifest', function () {
    actingAsBackendUser();
    $subscription = pwaSubscription();

    $this->postJson("/api/backend/customer-subscriptions/{$subscription->id}/generate-logos")
        ->assertOk();

    $iconsDirectory = "pwa-icons/{$subscription->id}/icons";

    Storage::disk('public')->assertExists($iconsDirectory.'/icon-192x192.png');
    Storage::disk('public')->assertExists($iconsDirectory.'/icon-512x512.png');
    Storage::disk('public')->assertExists($iconsDirectory.'/icon-192x192-maskable.png');
    Storage::disk('public')->assertExists($iconsDirectory.'/icon-512x512-maskable.png');

    $dimensions = getimagesize(Storage::disk('public')->path($iconsDirectory.'/icon-512x512.png'));
    expect($dimensions[0])->toBe(512)
        ->and($dimensions[1])->toBe(512);

    $api = $this->withHeaders([
        'Referer' => 'https://reporter.example.test/home',
    ])->getJson('/api/app_manifest')->assertOk();

    $apiIcons = manifestIconsByFilename($api->json('icons'));

    expect($apiIcons['icon-192x192.png']['purpose'])->toBe('any')
        ->and($apiIcons['icon-192x192.png']['sizes'])->toBe('192x192')
        ->and($apiIcons['icon-512x512.png']['purpose'])->toBe('any')
        ->and($apiIcons['icon-512x512.png']['sizes'])->toBe('512x512')
        ->and($apiIcons['icon-192x192-maskable.png']['purpose'])->toBe('maskable')
        ->and($apiIcons['icon-512x512-maskable.png']['purpose'])->toBe('maskable');

    $web = $this->withHeaders([
        'Referer' => 'https://reporter.example.test/home',
    ])->get('/app_manifest')->assertOk();

    $webIcons = manifestIconsByFilename($web->json('icons'));

    expect($web->json('name'))->toBe('Reporter')
        ->and($webIcons)->toHaveKey('icon-192x192.png')
        ->and($webIcons['icon-192x192-maskable.png']['purpose'])->toBe('maskable');
});

it('removes leftover icons when generate-logos runs again', function () {
    actingAsBackendUser();
    $subscription = pwaSubscription();
    $iconsDirectory = "pwa-icons/{$subscription->id}/icons";

    $this->postJson("/api/backend/customer-subscriptions/{$subscription->id}/generate-logos")
        ->assertOk();

    $leftover = $iconsDirectory.'/leftover-999x999.png';
    Storage::disk('public')->put($leftover, 'stale');
    Storage::disk('public')->assertExists($leftover);

    $this->postJson("/api/backend/customer-subscriptions/{$subscription->id}/generate-logos")
        ->assertOk();

    Storage::disk('public')->assertMissing($leftover);
    Storage::disk('public')->assertExists($iconsDirectory.'/icon-192x192.png');
    Storage::disk('public')->assertExists($iconsDirectory.'/icon-512x512.png');
});

it('returns 404 for the manifest when the host cannot be resolved', function () {
    $this->getJson('/api/app_manifest')->assertNotFound();
});

it('resolves the manifest host from origin and skips files without a size', function () {
    $subscription = pwaSubscription();
    $iconsDirectory = "pwa-icons/{$subscription->id}/icons";
    Storage::disk('public')->put($iconsDirectory.'/icon-192x192.png', 'png');
    Storage::disk('public')->put($iconsDirectory.'/readme.txt', 'skip me');

    $response = $this->withHeaders([
        'Origin' => 'https://reporter.example.test',
    ])->getJson('/api/app_manifest')->assertOk();

    $icons = manifestIconsByFilename($response->json('icons'));

    expect($icons)->toHaveKey('icon-192x192.png')
        ->and($icons)->not->toHaveKey('readme.txt');
});

it('resolves the manifest host from customer_url', function () {
    $subscription = pwaSubscription();
    Storage::disk('public')->put("pwa-icons/{$subscription->id}/icons/icon-192x192.png", 'png');

    $this->getJson('/api/app_manifest?customer_url='.urlencode('https://reporter.example.test'))
        ->assertOk()
        ->assertJsonPath('name', 'Reporter');
});
