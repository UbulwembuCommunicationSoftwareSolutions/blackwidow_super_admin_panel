<?php

use App\Models\SubscriptionType;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects mcp health without a token', function () {
    $this->getJson('/api/mcp/health')
        ->assertStatus(401);
});

it('returns mcp health for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/mcp/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure(['app', 'environment', 'status']);
});

it('returns subscription types for a sanctum user', function () {
    Sanctum::actingAs(User::factory()->create());
    SubscriptionType::factory()->create(['name' => 'Type A']);

    $this->getJson('/api/mcp/subscription-types')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Type A');
});
