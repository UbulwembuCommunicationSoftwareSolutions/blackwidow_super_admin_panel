<?php

use App\Http\Controllers\Api\CrmController;
use App\Http\Controllers\Api\GithubReleaseWebhookController;
use App\Http\Controllers\Api\McpSiteController;
use App\Http\Controllers\Api\V1\BrandingSyncController;
use App\Http\Controllers\Api\V1\CustomerSyncController;
use App\Http\Controllers\Api\V1\UserSyncController;
use App\Http\Controllers\CustomerSubscriptionController;
use App\Http\Controllers\GooglePlacesProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('user-login', 'App\Http\Controllers\CustomerUserController@login');
Route::get('customer/app-functions', [CustomerSubscriptionController::class, 'getAppFunctions']);
Route::get('customer/responder-functions', [CustomerSubscriptionController::class, 'getResponderAppFunctions']);
Route::get('customer_cms_url', [CustomerSubscriptionController::class, 'getCmsUrl']);
Route::get('app_manifest', [CustomerSubscriptionController::class, 'getManifest']);
Route::middleware('auth:sanctum')->post('/token-user', [CustomerSubscriptionController::class, 'checkLoggedIn']);
Route::middleware('auth:sanctum')->post('/sso-logout', [CustomerSubscriptionController::class, 'ssoLogout']);

/*
 * Canonical user sync contract for tenant apps (CMS, firearm, ...).
 * The legacy single-purpose aliases below are kept for tenants that have not
 * deployed yet; both routes run the same CustomerUserSyncService.
 */
Route::middleware('customer.bearer')->prefix('v1/sync')->group(function () {
    Route::get('users', [UserSyncController::class, 'index']);
    Route::post('users', [UserSyncController::class, 'upsert']);
    Route::post('users/archive', [UserSyncController::class, 'archive']);
    Route::post('users/restore', [UserSyncController::class, 'restore']);
    Route::post('users/password', [UserSyncController::class, 'password']);
    Route::post('users/password-reset-email', [UserSyncController::class, 'passwordResetEmail']);
    Route::get('branding', [BrandingSyncController::class, 'index']);
    Route::post('branding', [BrandingSyncController::class, 'upsert']);
});

Route::middleware('lms.bearer')->prefix('v1/sync')->group(function () {
    Route::get('customers', [CustomerSyncController::class, 'index']);
    Route::get('users/hub', [UserSyncController::class, 'hubIndex']);
    Route::get('branding/hub', [BrandingSyncController::class, 'hubIndex']);
});

Route::middleware('customer.bearer')->group(function () {
    Route::post('user-import', 'App\Http\Controllers\CustomerUserController@index');
    Route::post('create-user', 'App\Http\Controllers\CustomerUserController@store');
    Route::post('update-user', 'App\Http\Controllers\CustomerUserController@updateFromCMS');
    Route::post('get-user', 'App\Http\Controllers\CustomerUserController@getSingleUser');
    Route::post('update-password', 'App\Http\Controllers\CustomerUserController@updatePasswordFromCMS');
    Route::post('user-password', 'App\Http\Controllers\CustomerUserController@updatePassword');
    Route::post('deactivate-user', 'App\Http\Controllers\CustomerUserController@deactivateUser');
    Route::post('activate-user', 'App\Http\Controllers\CustomerUserController@activateUser');
    Route::post('archive-user', 'App\Http\Controllers\CustomerUserController@archiveUser');
    Route::post('restore-user', 'App\Http\Controllers\CustomerUserController@restoreUser');
    Route::post('urls', 'App\Http\Controllers\CustomerController@getUrls');
});

Route::post('/google-places-proxy', [GooglePlacesProxyController::class, 'proxy']);

Route::post('/webhooks/github/releases', GithubReleaseWebhookController::class)
    ->middleware('github.webhook');

// MCP / automation: JSON API (Sanctum bearer token; create via php artisan mcp:create-token).
// customer-subscription POST: optional trigger_site_deployment, force_site_deployment to queue the Forge site pipeline.
Route::middleware('auth:sanctum')->prefix('mcp')->group(function () {
    Route::get('/health', [McpSiteController::class, 'health']);
    Route::get('/overview', [McpSiteController::class, 'overview']);
    Route::get('/subscription-types', [McpSiteController::class, 'subscriptionTypes']);
    Route::get('/template-env-variables', [McpSiteController::class, 'templateEnvVariables']);
    Route::get('/template-env-variables/{id}', [McpSiteController::class, 'showTemplateEnvVariable'])->whereNumber('id');
    Route::post('/template-env-variables', [McpSiteController::class, 'storeTemplateEnvVariable']);
    Route::put('/template-env-variables/{id}', [McpSiteController::class, 'updateTemplateEnvVariable'])->whereNumber('id');
    Route::delete('/template-env-variables/{id}', [McpSiteController::class, 'destroyTemplateEnvVariable'])->whereNumber('id');
    Route::get('/env-variables', [McpSiteController::class, 'envVariables']);
    Route::get('/env-variables/{id}', [McpSiteController::class, 'showEnvVariable'])->whereNumber('id');
    Route::post('/env-variables', [McpSiteController::class, 'storeEnvVariable']);
    Route::put('/env-variables/{id}', [McpSiteController::class, 'updateEnvVariable'])->whereNumber('id');
    Route::delete('/env-variables/{id}', [McpSiteController::class, 'destroyEnvVariable'])->whereNumber('id');
    Route::get('/customers', [McpSiteController::class, 'customers']);
    Route::get('/customers/{id}', [McpSiteController::class, 'showCustomer'])->whereNumber('id');
    Route::post('/customers', [McpSiteController::class, 'storeCustomer']);
    Route::put('/customers/{id}', [McpSiteController::class, 'updateCustomer'])->whereNumber('id');
    Route::delete('/customers/{id}', [McpSiteController::class, 'destroyCustomer'])->whereNumber('id');
    Route::get('/customer-users', [McpSiteController::class, 'customerUsers']);
    Route::get('/customer-users/{id}', [McpSiteController::class, 'showCustomerUser'])->whereNumber('id');
    Route::get('/deployment-jobs', [McpSiteController::class, 'deploymentJobs']);
    Route::get('/deployment-jobs/{id}', [McpSiteController::class, 'showDeploymentJob'])->whereNumber('id');
    Route::get('/customer-subscriptions', [McpSiteController::class, 'customerSubscriptions']);
    Route::get('/customer-subscriptions/{id}/env-diff', [McpSiteController::class, 'envDiff'])->whereNumber('id');
    Route::get('/customer-subscriptions/{id}', [McpSiteController::class, 'showCustomerSubscription'])->whereNumber('id');
    Route::post('/customer-subscriptions', [McpSiteController::class, 'storeCustomerSubscription']);
    Route::put('/customer-subscriptions/{id}', [McpSiteController::class, 'updateCustomerSubscription'])->whereNumber('id');
    Route::delete('/customer-subscriptions/{id}', [McpSiteController::class, 'destroyCustomerSubscription'])->whereNumber('id');
});

// CRM: Sanctum bearer token with the `crm` ability (create via php artisan crm:create-token).
// customer-subscription POST: optional trigger_site_deployment, force_site_deployment to queue the Forge site pipeline.
Route::middleware(['auth:sanctum', 'abilities:crm'])->prefix('crm')->group(function () {
    Route::get('/health', [CrmController::class, 'health']);
    Route::get('/subscription-types', [CrmController::class, 'subscriptionTypes']);
    Route::get('/customers', [CrmController::class, 'customers']);
    Route::get('/customers/{id}', [CrmController::class, 'showCustomer'])->whereNumber('id');
    Route::post('/customers', [CrmController::class, 'storeCustomer']);
    Route::put('/customers/{id}', [CrmController::class, 'updateCustomer'])->whereNumber('id');
    Route::delete('/customers/{id}', [CrmController::class, 'destroyCustomer'])->whereNumber('id');
    Route::get('/customer-subscriptions', [CrmController::class, 'customerSubscriptions']);
    Route::get('/customer-subscriptions/{id}', [CrmController::class, 'showCustomerSubscription'])->whereNumber('id');
    Route::post('/customer-subscriptions', [CrmController::class, 'storeCustomerSubscription']);
    Route::put('/customer-subscriptions/{id}', [CrmController::class, 'updateCustomerSubscription'])->whereNumber('id');
    Route::delete('/customer-subscriptions/{id}', [CrmController::class, 'destroyCustomerSubscription'])->whereNumber('id');
});
