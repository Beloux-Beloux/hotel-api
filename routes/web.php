<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VerificationController;

Route::get('/', function () {
    return view('welcome');
});

// Email verification routes
Route::get('/email/verify', [VerificationController::class, 'verify'])
    ->name('verification.verify');
