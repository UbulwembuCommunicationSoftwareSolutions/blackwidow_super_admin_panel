<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('user-login', 'App\Http\Controllers\CustomerUserController@login');
Route::post('user-import', 'App\Http\Controllers\CustomerUserController@index');
Route::post('create-user', 'App\Http\Controllers\CustomerUserController@store');
Route::get('customer/app-functions', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getAppFunctions']);
Route::get('customer/responder-functions', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getResponderAppFunctions']);
Route::get('app_manifest', [\App\Http\Controllers\CustomerSubscriptionController::class, 'getManifest']);
Route::post('user-password', 'App\Http\Controllers\CustomerUserController@updatePassword');
Route::post('deactivate-user', 'App\Http\Controllers\CustomerUserController@deactivateUser');
Route::post('activate-user', 'App\Http\Controllers\CustomerUserController@activateUser');
Route::post('urls', 'App\Http\Controllers\CustomerController@getUrls');
Route::middleware('auth:sanctum')->post('/token-user',[\App\Http\Controllers\CustomerSubscriptionController::class,'checkLoggedIn']);

Route::post('/google-places-proxy', [\App\Http\Controllers\GooglePlacesProxyController::class, 'proxy']);

// User Sync API endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin-api/trigger-user-sync', [\App\Http\Controllers\UserSyncController::class, 'triggerSync']);
    Route::get('/admin-api/user-sync-status/{userId}', [\App\Http\Controllers\UserSyncController::class, 'getSyncStatus']);
    Route::get('/admin-api/user-sync-stats', [\App\Http\Controllers\UserSyncController::class, 'getSyncStats']);
});
