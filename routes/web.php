<?php

use App\Http\Controllers\CustomerSubscriptionController;
use App\Http\Controllers\SystemsApi\SystemsController;
use Illuminate\Support\Facades\Route;

// The Filament admin panel has been removed in favor of the external super admin frontend.
// Laravel and packages often expect a route named "login".
Route::get('/login', function () {
    return redirect()->away('https://super_admin_frontend-eumaqzrf.on-forge.com/');
})->name('login');

Route::get('/admin/{any?}', function () {
    return redirect()->away('https://super_admin_frontend-eumaqzrf.on-forge.com/');
})->where('any', '.*');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/app_manifest', [CustomerSubscriptionController::class, 'getManifest']);
Route::get('/customer_logos', [CustomerSubscriptionController::class, 'getLogos']);
Route::get('/customer_logo/specific', [CustomerSubscriptionController::class, 'getSpecificLogo']);
Route::get('/customer_logo/single', [CustomerSubscriptionController::class, 'getSingleLogo']);
Route::get('/customer-logo', [CustomerSubscriptionController::class, 'getSubscriptionLogo']);
Route::get('/customer/{id}', [CustomerSubscriptionController::class, 'show']);
Route::get('/customer-levels', [SystemsController::class, 'getSystemDescriptions']);
