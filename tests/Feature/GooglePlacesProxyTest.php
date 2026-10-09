<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('services.google.places_api_key', 'shared-places-key');
    Log::spy();
});

it('attributes a places call to the calling site domain', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'predictions' => [],
        ], 200, ['Content-Type' => 'application/json']),
    ]);

    $this->withHeaders([
        'Origin' => 'https://acme.blackwidow.test',
    ])->postJson('/api/google-places-proxy', [
        'endpoint' => 'place/autocomplete/json',
        'input' => 'main road',
        'key' => 'caller-must-not-override',
    ])->assertSuccessful()
        ->assertJsonPath('status', 'OK');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Referer', 'https://acme.blackwidow.test/')
            && str_contains($request->url(), 'key=shared-places-key')
            && str_contains($request->url(), 'place/autocomplete/json')
            && ! str_contains($request->url(), 'caller-must-not-override');
    });

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'google_places.usage'
                && $context['domain'] === 'acme.blackwidow.test'
                && $context['method'] === 'place/autocomplete/json'
                && $context['status'] === 'OK';
        });
});

it('caches a place details lookup for the same domain', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'result' => ['name' => 'Depot'],
        ]),
    ]);

    $payload = [
        'endpoint' => 'place/details/json',
        'place_id' => 'abc',
    ];

    $this->withHeaders(['Origin' => 'https://acme.blackwidow.test'])
        ->postJson('/api/google-places-proxy', $payload)
        ->assertSuccessful();

    $this->withHeaders(['Origin' => 'https://acme.blackwidow.test'])
        ->postJson('/api/google-places-proxy', $payload)
        ->assertSuccessful()
        ->assertJsonPath('result.name', 'Depot');

    Http::assertSentCount(1);
});

it('does not call Google for an unsupported endpoint', function () {
    Http::fake();

    $this->postJson('/api/google-places-proxy', [
        'endpoint' => 'geocode/json',
        'address' => 'somewhere',
    ])->assertUnprocessable()
        ->assertJsonPath('status', 'INVALID_REQUEST');

    Http::assertNothingSent();
});

it('records an unknown domain when the caller sends no origin', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['status' => 'OK', 'predictions' => []]),
    ]);

    $this->postJson('/api/google-places-proxy', [
        'endpoint' => 'place/autocomplete/json',
        'input' => 'main road',
    ])->assertSuccessful();

    Http::assertSent(function ($request): bool {
        return ! $request->hasHeader('Referer');
    });

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'google_places.usage'
                && $context['domain'] === 'unknown';
        });
});
