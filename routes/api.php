<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('user-login', 'App\Http\Controllers\CustomerUserController@login');
Route::post('user-import', 'App\Http\Controllers\CustomerUserController@index');
