<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\MasterSupervisor;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Name this Horizon after the site (HORIZON_NAME, the domain) so the dashboard and the supervisor list tell the
        // sites on a shared server apart. Falls back to the hostname, which is what Horizon used before.
        MasterSupervisor::determineNameUsing(fn () => config('horizon.name') ? Str::slug(str_replace('.', '-', (string) config('horizon.name'))) : Str::slug(gethostname()));

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                'jacques@ncloud.africa'
            ]);
        });
    }
}
