<?php

use App\Http\Controllers\Api\Backend\AuthController;
use App\Http\Controllers\Api\Backend\CustomerController;
use App\Http\Controllers\Api\Backend\CustomerSubscriptionController;
use App\Http\Controllers\Api\Backend\CustomerUserController;
use App\Http\Controllers\Api\Backend\DeploymentScriptController;
use App\Http\Controllers\Api\Backend\DeploymentTemplateController;
use App\Http\Controllers\Api\Backend\EnvVariablesController;
use App\Http\Controllers\Api\Backend\ForgeServerController;
use App\Http\Controllers\Api\Backend\NginxTemplateController;
use App\Http\Controllers\Api\Backend\RoleController;
use App\Http\Controllers\Api\Backend\SearchController;
use App\Http\Controllers\Api\Backend\SubscriptionTypeController;
use App\Http\Controllers\Api\Backend\TemplateEnvVariablesController;
use App\Http\Controllers\Api\Backend\UserController;
use App\Http\Controllers\Api\Backend\UserCustomerController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', [AuthController::class, 'me'])->name('user');

    Route::post('/customers/{id}/restore', [CustomerController::class, 'restore'])->whereNumber('id');
    Route::delete('/customers/{id}/force', [CustomerController::class, 'forceDestroy'])->whereNumber('id');
    Route::get('/customers/{id}/credentials', [CustomerController::class, 'credentials'])->whereNumber('id');
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/customers/{id}', [CustomerController::class, 'update'])->whereNumber('id');
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->whereNumber('id');

    Route::post('/customer-subscriptions/verify-domain', [CustomerSubscriptionController::class, 'verifyDomain']);
    Route::post('/customer-subscriptions/{id}/recreate-site', [CustomerSubscriptionController::class, 'recreateSite'])->whereNumber('id');
    Route::post('/customer-subscriptions/{id}/logos', [CustomerSubscriptionController::class, 'uploadLogos'])->whereNumber('id');
    Route::post('/customer-subscriptions/{id}/generate-logos', [CustomerSubscriptionController::class, 'generateLogos'])->whereNumber('id');
    Route::post('/customer-subscriptions/{id}/deploy', [CustomerSubscriptionController::class, 'deploy'])->whereNumber('id');
    Route::post('/customer-subscriptions/{id}/pull-env', [CustomerSubscriptionController::class, 'pullEnv'])->whereNumber('id');
    Route::put('/customer-subscriptions/{id}/server', [CustomerSubscriptionController::class, 'updateServer'])->whereNumber('id');
    Route::get('/customer-subscriptions/{id}/pipeline-steps', [CustomerSubscriptionController::class, 'pipelineSteps'])->whereNumber('id');
    Route::post('/customer-subscriptions/{id}/pipeline-steps/{index}', [CustomerSubscriptionController::class, 'queuePipelineStep'])->whereNumber(['id', 'index']);
    Route::get('/customer-subscriptions/{id}/deployment-jobs', [CustomerSubscriptionController::class, 'deploymentJobs'])->whereNumber('id');
    Route::get('/customer-subscriptions', [CustomerSubscriptionController::class, 'index']);
    Route::post('/customer-subscriptions', [CustomerSubscriptionController::class, 'store']);
    Route::get('/customer-subscriptions/{id}', [CustomerSubscriptionController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/customer-subscriptions/{id}', [CustomerSubscriptionController::class, 'update'])->whereNumber('id');
    Route::delete('/customer-subscriptions/{id}', [CustomerSubscriptionController::class, 'destroy'])->whereNumber('id');

    Route::post('/customer-users/{id}/restore', [CustomerUserController::class, 'restore'])->whereNumber('id');
    Route::delete('/customer-users/{id}/force', [CustomerUserController::class, 'forceDestroy'])->whereNumber('id');
    Route::post('/customer-users/{id}/update-password', [CustomerUserController::class, 'updatePassword'])->whereNumber('id');
    Route::post('/customer-users/{id}/send-welcome-email', [CustomerUserController::class, 'sendWelcomeEmail'])->whereNumber('id');
    Route::post('/customer-users/{id}/send-login-email', [CustomerUserController::class, 'sendLoginEmail'])->whereNumber('id');
    Route::put('/customer-users/{id}/access-rights', [CustomerUserController::class, 'updateAccessRights'])->whereNumber('id');
    Route::get('/customer-users', [CustomerUserController::class, 'index']);
    Route::post('/customer-users', [CustomerUserController::class, 'store']);
    Route::get('/customer-users/{id}', [CustomerUserController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/customer-users/{id}', [CustomerUserController::class, 'update'])->whereNumber('id');
    Route::delete('/customer-users/{id}', [CustomerUserController::class, 'destroy'])->whereNumber('id');

    Route::get('/search', [SearchController::class, 'index']);

    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/roles/{id}', [RoleController::class, 'update'])->whereNumber('id');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id');

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/users/{id}', [UserController::class, 'update'])->whereNumber('id');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->whereNumber('id');

    Route::post('/user-customers/{id}/restore', [UserCustomerController::class, 'restore'])->whereNumber('id');
    Route::delete('/user-customers/{id}/force', [UserCustomerController::class, 'forceDestroy'])->whereNumber('id');
    Route::get('/user-customers', [UserCustomerController::class, 'index']);
    Route::post('/user-customers', [UserCustomerController::class, 'store']);
    Route::get('/user-customers/{id}', [UserCustomerController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/user-customers/{id}', [UserCustomerController::class, 'update'])->whereNumber('id');
    Route::delete('/user-customers/{id}', [UserCustomerController::class, 'destroy'])->whereNumber('id');

    Route::get('/deployment-scripts', [DeploymentScriptController::class, 'index']);
    Route::post('/deployment-scripts', [DeploymentScriptController::class, 'store']);
    Route::get('/deployment-scripts/{id}', [DeploymentScriptController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/deployment-scripts/{id}', [DeploymentScriptController::class, 'update'])->whereNumber('id');
    Route::delete('/deployment-scripts/{id}', [DeploymentScriptController::class, 'destroy'])->whereNumber('id');

    Route::get('/deployment-templates', [DeploymentTemplateController::class, 'index']);
    Route::post('/deployment-templates', [DeploymentTemplateController::class, 'store']);
    Route::get('/deployment-templates/{id}', [DeploymentTemplateController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/deployment-templates/{id}', [DeploymentTemplateController::class, 'update'])->whereNumber('id');
    Route::delete('/deployment-templates/{id}', [DeploymentTemplateController::class, 'destroy'])->whereNumber('id');

    Route::get('/env-variables', [EnvVariablesController::class, 'index']);
    Route::post('/env-variables', [EnvVariablesController::class, 'store']);
    Route::get('/env-variables/{id}', [EnvVariablesController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/env-variables/{id}', [EnvVariablesController::class, 'update'])->whereNumber('id');
    Route::delete('/env-variables/{id}', [EnvVariablesController::class, 'destroy'])->whereNumber('id');

    Route::get('/template-env-variables', [TemplateEnvVariablesController::class, 'index']);
    Route::post('/template-env-variables', [TemplateEnvVariablesController::class, 'store']);
    Route::get('/template-env-variables/{id}', [TemplateEnvVariablesController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/template-env-variables/{id}', [TemplateEnvVariablesController::class, 'update'])->whereNumber('id');
    Route::delete('/template-env-variables/{id}', [TemplateEnvVariablesController::class, 'destroy'])->whereNumber('id');

    Route::post('/forge-servers/sync', [ForgeServerController::class, 'sync']);
    Route::get('/forge-servers', [ForgeServerController::class, 'index']);
    Route::post('/forge-servers', [ForgeServerController::class, 'store']);
    Route::get('/forge-servers/{id}', [ForgeServerController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/forge-servers/{id}', [ForgeServerController::class, 'update'])->whereNumber('id');
    Route::delete('/forge-servers/{id}', [ForgeServerController::class, 'destroy'])->whereNumber('id');

    Route::get('/nginx-templates', [NginxTemplateController::class, 'index']);
    Route::post('/nginx-templates', [NginxTemplateController::class, 'store']);
    Route::get('/nginx-templates/{id}', [NginxTemplateController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/nginx-templates/{id}', [NginxTemplateController::class, 'update'])->whereNumber('id');
    Route::delete('/nginx-templates/{id}', [NginxTemplateController::class, 'destroy'])->whereNumber('id');

    Route::post('/subscription-types/{id}/restore', [SubscriptionTypeController::class, 'restore'])->whereNumber('id');
    Route::delete('/subscription-types/{id}/force', [SubscriptionTypeController::class, 'forceDestroy'])->whereNumber('id');
    Route::get('/subscription-types', [SubscriptionTypeController::class, 'index']);
    Route::post('/subscription-types', [SubscriptionTypeController::class, 'store']);
    Route::get('/subscription-types/{id}', [SubscriptionTypeController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/subscription-types/{id}', [SubscriptionTypeController::class, 'update'])->whereNumber('id');
    Route::delete('/subscription-types/{id}', [SubscriptionTypeController::class, 'destroy'])->whereNumber('id');
});
