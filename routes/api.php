<?php

use App\Http\Controllers\Api\McpSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('user-login', 'App\Http\Controllers\CustomerUserController@login');
Route::post('user-import', 'App\Http\Controllers\CustomerUserController@index');
Route::post('create-user', 'App\Http\Controllers\CustomerUserController@store');
Route::post('update-user', 'App\Http\Controllers\CustomerUserController@updateFromCMS');
Route::post('get-user', 'App\Http\Controllers\CustomerUserController@getSingleUser');
Route::post('update-password', 'App\Http\Controllers\CustomerUserController@updatePasswordFromCMS');
Route::get('customer/app-functions', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getAppFunctions']);
Route::get('customer/responder-functions', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getResponderAppFunctions']);
Route::get('app_manifest', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getManifest']);
Route::post('user-password', 'App\Http\Controllers\CustomerUserController@updatePassword');
Route::post('deactivate-user', 'App\Http\Controllers\CustomerUserController@deactivateUser');
Route::post('activate-user', 'App\Http\Controllers\CustomerUserController@activateUser');
Route::post('urls', 'App\Http\Controllers\CustomerController@getUrls');
Route::middleware('auth:sanctum')->post('/token-user', [\App\Http\Controllers\CustomerSubscriptionController::class, 'checkLoggedIn']);

Route::post('/google-places-proxy', [\App\Http\Controllers\GooglePlacesProxyController::class, 'proxy']);

// User Sync API endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin-api/trigger-user-sync', [\App\Http\Controllers\UserSyncController::class, 'triggerSync']);
    Route::get('/admin-api/user-sync-status/{userId}', [\App\Http\Controllers\UserSyncController::class, 'getSyncStatus']);
    Route::get('/admin-api/user-sync-stats', [\App\Http\Controllers\UserSyncController::class, 'getSyncStats']);
});

// MCP / automation: JSON API (Sanctum bearer token; create via php artisan mcp:create-token).
// customer-subscription POST: optional trigger_site_deployment, force_site_deployment to queue the Forge site pipeline.
Route::middleware('auth:sanctum')->prefix('mcp')->group(function () {
    Route::get('/health', [McpSiteController::class, 'health']);
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
    Route::get('/customer-subscriptions', [McpSiteController::class, 'customerSubscriptions']);
    Route::get('/customer-subscriptions/{id}', [McpSiteController::class, 'showCustomerSubscription'])->whereNumber('id');
    Route::post('/customer-subscriptions', [McpSiteController::class, 'storeCustomerSubscription']);
    Route::put('/customer-subscriptions/{id}', [McpSiteController::class, 'updateCustomerSubscription'])->whereNumber('id');
    Route::delete('/customer-subscriptions/{id}', [McpSiteController::class, 'destroyCustomerSubscription'])->whereNumber('id');
});
