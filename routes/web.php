<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;


Route::get('/login', function () {
    return response()->json([
        'status' => false,
        'message' => 'Please login to access this resource'
    ], 401);
})->name('login');


// routes/web.php


