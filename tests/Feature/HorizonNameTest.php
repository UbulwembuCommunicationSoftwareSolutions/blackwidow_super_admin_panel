<?php

use Illuminate\Support\Str;
use Laravel\Horizon\MasterSupervisor;

afterEach(fn () => MasterSupervisor::determineNameUsing(fn () => Str::slug(gethostname())));

it('names the master supervisor after HORIZON_NAME so sites on one server can be told apart', function () {
    config(['horizon.name' => 'superadmin-api.blackwidow.org.za']);
    app(App\Providers\HorizonServiceProvider::class, ['app' => app()])->boot();

    expect(MasterSupervisor::basename())->toBe('superadmin-api-blackwidow-org-za')
        ->and(MasterSupervisor::name())->toStartWith('superadmin-api-blackwidow-org-za-');
});

it('falls back to the hostname when HORIZON_NAME is not set', function () {
    config(['horizon.name' => null]);
    app(App\Providers\HorizonServiceProvider::class, ['app' => app()])->boot();

    expect(MasterSupervisor::basename())->toBe(Str::slug(gethostname()));
});
