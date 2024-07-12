<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerSubscriptionController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/customer_logo_1', [CustomerSubscriptionController::class, 'getLogoOne']);
Route::get('/customer_logo_2', [CustomerSubscriptionController::class, 'getLogoTwo']);
Route::get('/customer_logo_3', [CustomerSubscriptionController::class, 'getLogoThree']);
Route::get('/customer_logo_4', [CustomerSubscriptionController::class, 'getLogoFour']);
Route::get('/customer_logo_5', [CustomerSubscriptionController::class, 'getLogoFive']);
