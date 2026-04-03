<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\RouteListController;

Route::get('/login', function () {
    return response()->json([
        'status' => false,
        'message' => 'Please login to access this resource'
    ], 401);
})->name('login');

Route::get('/api-routes', [RouteListController::class, 'index']);
// routes/web.php


