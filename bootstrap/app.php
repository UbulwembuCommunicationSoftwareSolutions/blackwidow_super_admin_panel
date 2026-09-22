<?php

use App\Http\Middleware\AuthenticateExternalTokenCookie;
use App\Http\Middleware\EnsureActiveCustomerAdmin;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerifyCustomerBearerToken;
use App\Http\Middleware\VerifyGithubWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/backend')
                ->name('backend.')
                ->group(base_path('routes/backend-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: [
            'external_token',
        ]);

        $middleware->api(prepend: [
            AuthenticateExternalTokenCookie::class,
        ]);

        $middleware->web(remove: [
            Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        $middleware->web(append: [
            VerifyCsrfToken::class,
        ]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'customer.bearer' => VerifyCustomerBearerToken::class,
            'customer.admin' => EnsureActiveCustomerAdmin::class,
            'github.webhook' => VerifyGithubWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
