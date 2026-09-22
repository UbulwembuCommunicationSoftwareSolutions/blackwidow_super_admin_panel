<?php

namespace App\Providers;

use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\SubscriptionType;
use App\Observers\CustomerSubscriptionObserver;
use App\Observers\CustomerUserObserver;
use App\Observers\SubscriptionTypeObserver;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Gate::policy(Role::class, RolePolicy::class);

        CustomerSubscription::observe(CustomerSubscriptionObserver::class);
        CustomerUser::observe(CustomerUserObserver::class);
        SubscriptionType::observe(SubscriptionTypeObserver::class);
    }
}
