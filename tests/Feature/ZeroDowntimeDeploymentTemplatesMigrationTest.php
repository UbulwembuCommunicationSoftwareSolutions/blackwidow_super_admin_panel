<?php

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use App\Services\DeploymentScriptRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function runZeroDowntimeTemplatesMigration(): void
{
    $migration = require database_path('migrations/2026_10_23_090000_use_forge_zero_downtime_macros_in_deployment_templates.php');
    $migration->up();
}

it('writes a zero downtime template for every subscription type that deploys code', function () {
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13] as $id) {
        SubscriptionType::factory()->create(['id' => $id]);
    }
    DeploymentTemplate::query()->create([
        'subscription_type_id' => 1,
        'script' => "cd /home/forge/#WEBSITE_URL#\ngit pull origin \$FORGE_SITE_BRANCH\n",
    ]);

    runZeroDowntimeTemplatesMigration();
    runZeroDowntimeTemplatesMigration();

    $templates = DeploymentTemplate::query()->get()->keyBy('subscription_type_id');

    expect($templates->keys()->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6, 7, 9, 10, 12, 13]);

    foreach ($templates as $template) {
        expect($template->script)
            ->toStartWith('$CREATE_RELEASE()')
            ->toContain('cd $FORGE_RELEASE_DIRECTORY')
            ->toContain('$ACTIVATE_RELEASE()')
            ->not->toContain('#WEBSITE_URL#')
            ->not->toContain('git pull')
            ->not->toMatch('/^\s+\$CREATE_RELEASE/');
    }

    expect($templates[13]->script)->toContain('$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader');
});

it('renders the console template unchanged apart from the deploy lock on a zero downtime site', function () {
    SubscriptionType::factory()->create(['id' => 1]);
    runZeroDowntimeTemplatesMigration();

    $sub = CustomerSubscription::factory()->create([
        'subscription_type_id' => 1,
        'customer_id' => Customer::factory(),
        'domain' => 'demo.example.test',
        'server_id' => null,
        'forge_site_id' => null,
    ]);
    $sub->forceFill(['zero_downtime_deployments' => true])->save();

    [$script] = app(DeploymentScriptRenderer::class)->render($sub->fresh());

    expect(substr_count($script->script, '$CREATE_RELEASE()'))->toBe(1)
        ->and(substr_count($script->script, '$ACTIVATE_RELEASE()'))->toBe(1)
        ->and($script->script)->toEndWith(DeploymentTemplate::query()->where('subscription_type_id', 1)->value('script'));
});
