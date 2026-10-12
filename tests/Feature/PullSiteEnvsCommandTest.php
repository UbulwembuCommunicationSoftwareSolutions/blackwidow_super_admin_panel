<?php

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\SubscriptionType;
use App\Support\SiteEnv\SiteEnvReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string|Throwable>  $envBySubscriptionId
 */
function fakeSiteEnvs(array $envBySubscriptionId): void
{
    app()->instance(SiteEnvReader::class, new class($envBySubscriptionId) extends SiteEnvReader
    {
        public function __construct(private array $envs) {}

        public function read(CustomerSubscription $subscription): string
        {
            $env = $this->envs[$subscription->id];

            return $env instanceof Throwable ? throw $env : $env;
        }
    });
}

/**
 * @return array<string, string|null>
 */
function envRows(CustomerSubscription $subscription): array
{
    return EnvVariables::query()
        ->where('customer_subscription_id', $subscription->id)
        ->orderBy('key')
        ->pluck('value', 'key')
        ->all();
}

beforeEach(function () {
    $this->subscription = CustomerSubscription::factory()->create([
        'subscription_type_id' => SubscriptionType::factory()->create()->id,
        'server_id' => 11,
        'forge_site_id' => 22,
    ]);

    foreach (['APP_KEY' => 'base64:stale', 'MAIL_PASSWORD' => 'held-here', 'COOKIE_DOMAIN' => '.blackwidow.org.za', 'APP_ENV' => 'production'] as $key => $value) {
        EnvVariables::query()->create(['customer_subscription_id' => $this->subscription->id, 'key' => $key, 'value' => $value]);
    }

    $this->siteEnv = implode("\n", [
        '# comment',
        'APP_ENV=production',
        'APP_KEY=base64:real',
        'MAIL_PASSWORD=',
        'SUPERADMIN_API_URL=https://superadmin-api.blackwidow.org.za',
        'MAIL_FROM_NAME="${APP_NAME}"',
        'GREETING="say \\"hi\\""',
        'FORGE_API_KEY=never-store-me',
    ]);
});

it('reports the differences without writing on a dry run', function () {
    fakeSiteEnvs([$this->subscription->id => $this->siteEnv]);
    $before = envRows($this->subscription);

    $this->artisan('app:pull-site-envs', ['--keys' => true])
        ->expectsOutputToContain('3 key(s) added, 1 changed, 1 kept')
        ->doesntExpectOutputToContain('base64:real')
        ->assertSuccessful();

    expect(envRows($this->subscription))->toBe($before);
});

it('takes the site values, keeps a row the site has empty, and leaves rows the site lacks', function () {
    fakeSiteEnvs([$this->subscription->id => $this->siteEnv]);

    $this->artisan('app:pull-site-envs', ['--apply' => true])->assertSuccessful();

    expect(envRows($this->subscription))->toBe([
        'APP_ENV' => 'production',
        'APP_KEY' => 'base64:real',
        'COOKIE_DOMAIN' => '.blackwidow.org.za',
        'GREETING' => 'say "hi"',
        'MAIL_FROM_NAME' => '${APP_NAME}',
        'MAIL_PASSWORD' => 'held-here',
        'SUPERADMIN_API_URL' => 'https://superadmin-api.blackwidow.org.za',
    ])->and($this->subscription->fresh()->env)->toBe($this->siteEnv);
});

it('carries on past a site it cannot read and skips ones without a Forge site', function () {
    $unreachable = CustomerSubscription::factory()->create(['server_id' => 11, 'forge_site_id' => 33]);
    $noForge = CustomerSubscription::factory()->create(['server_id' => null, 'forge_site_id' => null]);

    fakeSiteEnvs([
        $this->subscription->id => $this->siteEnv,
        $unreachable->id => new RuntimeException('Forge said no'),
    ]);

    $this->artisan('app:pull-site-envs', ['--apply' => true])
        ->expectsOutputToContain('1 failed, 1 skipped')
        ->assertFailed();

    expect(envRows($this->subscription)['APP_KEY'])->toBe('base64:real')
        ->and(envRows($unreachable))->toBe([])
        ->and(envRows($noForge))->toBe([]);
});
