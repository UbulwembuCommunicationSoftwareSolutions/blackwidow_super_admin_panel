<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerSubscriptionController;

// Laravel and packages often expect a route named "login". Filament auth lives at /admin/login;
// register this name after the panel is booted (Filament route names are not available while loading web.php).
Route::get('/login', function () {
    if (Route::has('filament.admin.auth.login')) {
        return redirect()->route('filament.admin.auth.login');
    }

    return redirect()->to('/admin/login');
})->name('login');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/customer_logos', [CustomerSubscriptionController::class, 'getLogos']);
Route::get('/customer_logo/specific', [CustomerSubscriptionController::class, 'getSpecificLogo']);
Route::get('/customer_logo/single', [CustomerSubscriptionController::class, 'getSingleLogo']);
Route::get('/customer-logo', [CustomerSubscriptionController::class, 'getSubscriptionLogo']);
Route::get('/customer/{id}', [CustomerSubscriptionController::class, 'show']);
Route::get('/customer-levels', [\App\Http\Controllers\SystemsApi\SystemsController::class, 'getSystemDescriptions']);

