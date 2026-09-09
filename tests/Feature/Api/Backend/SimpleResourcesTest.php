<?php

use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use App\Models\EnvVariables;
use App\Models\ForgeServer;
use App\Models\NginxTemplate;
use App\Models\SubscriptionType;
use App\Models\TemplateEnvVariables;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake();
});

it('rejects simple resources without a token', function (string $path) {
    $this->getJson("/api/backend/{$path}")->assertUnauthorized();
})->with([
    'deployment-scripts',
    'deployment-templates',
    'env-variables',
    'template-env-variables',
    'forge-servers',
    'nginx-templates',
    'subscription-types',
]);

it('forbids simple resources without Shield permissions', function (string $path) {
    actingAsBackendForbidden();

    $this->getJson("/api/backend/{$path}")->assertForbidden();
})->with([
    'deployment-scripts',
    'deployment-templates',
    'env-variables',
    'template-env-variables',
    'forge-servers',
    'nginx-templates',
    'subscription-types',
]);

it('can crud a deployment script', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);

    $create = $this->postJson('/api/backend/deployment-scripts', [
        'script' => 'echo hi',
        'customer_subscription_id' => $sub->id,
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson("/api/backend/deployment-scripts?customer_subscription_id={$sub->id}")
        ->assertOk()
        ->assertJsonPath('data.0.script', 'echo hi');
    $this->putJson("/api/backend/deployment-scripts/{$id}", ['script' => 'echo bye'])
        ->assertOk()
        ->assertJsonPath('data.script', 'echo bye');
    $this->deleteJson("/api/backend/deployment-scripts/{$id}")->assertOk();
    expect(DeploymentScript::query()->find($id))->toBeNull();
});

it('can crud a deployment template', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();

    $create = $this->postJson('/api/backend/deployment-templates', [
        'script' => 'deploy',
        'subscription_type_id' => $type->id,
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson("/api/backend/deployment-templates?subscription_type_id={$type->id}")->assertOk();
    $this->putJson("/api/backend/deployment-templates/{$id}", ['script' => 'deploy2'])
        ->assertOk()
        ->assertJsonPath('data.script', 'deploy2');
    $this->deleteJson("/api/backend/deployment-templates/{$id}")->assertOk();
    expect(DeploymentTemplate::query()->find($id))->toBeNull();
});

it('can crud an env variable', function () {
    actingAsBackendUser();
    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create(['project_type' => 'static'])->id,
    ]);

    $create = $this->postJson('/api/backend/env-variables', [
        'customer_subscription_id' => $sub->id,
        'key' => 'APP_DEBUG',
        'value' => 'false',
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson("/api/backend/env-variables?customer_subscription_id={$sub->id}")
        ->assertOk()
        ->assertJsonPath('data.0.key', 'APP_DEBUG');
    $this->putJson("/api/backend/env-variables/{$id}", ['value' => 'true'])
        ->assertOk()
        ->assertJsonPath('data.value', 'true');
    $this->deleteJson("/api/backend/env-variables/{$id}")->assertOk();
    expect(EnvVariables::query()->find($id))->toBeNull();
});

it('can crud a template env variable', function () {
    actingAsBackendUser();
    $type = SubscriptionType::factory()->create();

    $create = $this->postJson('/api/backend/template-env-variables', [
        'subscription_type_id' => $type->id,
        'key' => 'APP_NAME',
        'value' => 'Demo',
        'requires_manual_fill' => false,
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson("/api/backend/template-env-variables?subscription_type_id={$type->id}")->assertOk();
    $this->putJson("/api/backend/template-env-variables/{$id}", ['value' => 'Prod'])
        ->assertOk()
        ->assertJsonPath('data.value', 'Prod');
    $this->getJson("/api/backend/template-env-variables/{$id}")->assertOk();
    $this->deleteJson("/api/backend/template-env-variables/{$id}")->assertOk();
    expect(TemplateEnvVariables::query()->find($id))->toBeNull();
});

it('can crud a forge server and rejects unauthenticated sync', function () {
    $this->postJson('/api/backend/forge-servers/sync')->assertUnauthorized();

    actingAsBackendUser();
    $create = $this->postJson('/api/backend/forge-servers', [
        'forge_server_id' => 77,
        'name' => 'Forge One',
        'ip_address' => '10.0.0.1',
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson('/api/backend/forge-servers')->assertOk()->assertJsonPath('data.0.name', 'Forge One');
    $this->putJson("/api/backend/forge-servers/{$id}", ['name' => 'Forge Two'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Forge Two');
    $this->deleteJson("/api/backend/forge-servers/{$id}")->assertOk();
    expect(ForgeServer::query()->find($id))->toBeNull();
});

it('forbids forge server sync without create permission', function () {
    actingAsBackendForbidden();

    $this->postJson('/api/backend/forge-servers/sync')->assertForbidden();
});

it('returns a handled error when forge sync cannot reach Forge', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/forge-servers/sync')
        ->assertStatus(502)
        ->assertJsonStructure(['message']);
});

it('can crud an nginx template', function () {
    actingAsBackendUser();

    $create = $this->postJson('/api/backend/nginx-templates', [
        'name' => 'Default',
        'server_id' => 10,
        'template_id' => 3,
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson('/api/backend/nginx-templates?server_id=10')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Default');
    $this->putJson("/api/backend/nginx-templates/{$id}", ['name' => 'Alt'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Alt');
    $this->deleteJson("/api/backend/nginx-templates/{$id}")->assertOk();
    expect(NginxTemplate::query()->find($id))->toBeNull();
});

it('can crud restore and force delete a subscription type', function () {
    actingAsBackendUser();

    $create = $this->postJson('/api/backend/subscription-types', [
        'name' => 'Console',
        'github_repo' => 'org/console',
        'branch' => 'main',
        'project_type' => 'php',
        'master_version' => '1.0.0',
    ])->assertCreated();

    $id = $create->json('data.id');
    $this->getJson('/api/backend/subscription-types')->assertOk();
    $this->putJson("/api/backend/subscription-types/{$id}", ['name' => 'Console 2'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Console 2');
    $this->deleteJson("/api/backend/subscription-types/{$id}")->assertOk();
    $this->assertSoftDeleted('subscription_types', ['id' => $id]);
    $this->postJson("/api/backend/subscription-types/{$id}/restore")->assertOk();
    $this->deleteJson("/api/backend/subscription-types/{$id}")->assertOk();
    $this->deleteJson("/api/backend/subscription-types/{$id}/force")->assertOk();
    $this->assertDatabaseMissing('subscription_types', ['id' => $id]);
});

it('returns 404 for missing simple resources', function () {
    actingAsBackendUser();

    $this->getJson('/api/backend/deployment-scripts/999999')->assertNotFound();
    $this->getJson('/api/backend/forge-servers/999999')->assertNotFound();
    $this->getJson('/api/backend/subscription-types/999999')->assertNotFound();
});

it('validates simple resource creates', function () {
    actingAsBackendUser();

    $this->postJson('/api/backend/deployment-scripts', [])->assertStatus(422);
    $this->postJson('/api/backend/deployment-templates', [])->assertStatus(422);
    $this->postJson('/api/backend/env-variables', [])->assertStatus(422);
    $this->postJson('/api/backend/template-env-variables', [])->assertStatus(422);
    $this->postJson('/api/backend/forge-servers', [])->assertStatus(422);
    $this->postJson('/api/backend/nginx-templates', [])->assertStatus(422);
    $this->postJson('/api/backend/subscription-types', [])->assertStatus(422);
});
