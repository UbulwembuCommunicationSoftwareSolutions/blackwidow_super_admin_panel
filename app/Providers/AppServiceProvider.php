<?php

namespace App\Providers;

use App\Models\CustomerUser;
use App\Observers\UserSyncObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the UserSyncObserver
        CustomerUser::observe(UserSyncObserver::class);
    }
}
