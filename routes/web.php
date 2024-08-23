<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerSubscriptionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/customer_logos', [CustomerSubscriptionController::class, 'getLogos']);
Route::get('/customer_logo/specific', [CustomerSubscriptionController::class, 'getSpecificLogo']);
Route::get('/customer/{id}', [CustomerSubscriptionController::class, 'show']);

