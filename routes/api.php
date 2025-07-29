<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Hotels - These routes don't require hotel selection
    Route::get('/hotels', [\App\Http\Controllers\Api\HotelController::class, 'index']);
    Route::get('/hotels/current', [\App\Http\Controllers\Api\HotelController::class, 'current']);
    Route::post('/hotels/switch', [\App\Http\Controllers\Api\HotelController::class, 'switch']);
    
    // Routes that require hotel selection
    Route::middleware(['ensure.hotel.selected'])->group(function () {
        // Dashboard
        Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats'])
            ->middleware('permission:dashboard.view');
        Route::get('/dashboard/activities', [\App\Http\Controllers\Api\DashboardController::class, 'activities'])
            ->middleware('permission:dashboard.view');
        
        // Hotel management
        Route::get('/hotels/statistics', [\App\Http\Controllers\Api\HotelController::class, 'statistics']);
        Route::put('/hotels/settings', [\App\Http\Controllers\Api\HotelController::class, 'updateSettings']);
        
        // Room Types
        Route::apiResource('room-types', \App\Http\Controllers\Api\RoomTypeController::class)
            ->only(['index', 'show'])
            ->middleware('permission:rooms.view');
        Route::get('/room-types/{roomType}/availability', [\App\Http\Controllers\Api\RoomTypeController::class, 'availability'])
            ->middleware('permission:rooms.view');
        
        // Rooms
        Route::get('/rooms/availability', [\App\Http\Controllers\Api\RoomController::class, 'availability'])
            ->middleware('permission:rooms.view');
        Route::get('/rooms/statistics', [\App\Http\Controllers\Api\RoomController::class, 'statistics'])
            ->middleware('permission:dashboard.view');
        Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class)
            ->only(['index', 'show', 'update'])
            ->middleware('permission:rooms.view');
        
        // Guests
        Route::get('/guests/search', [\App\Http\Controllers\Api\GuestController::class, 'search'])
            ->middleware('permission:reservations.view');
        Route::get('/guests/{guest}/statistics', [\App\Http\Controllers\Api\GuestController::class, 'statistics'])
            ->middleware('permission:reservations.view');
        Route::apiResource('guests', \App\Http\Controllers\Api\GuestController::class)
            ->middleware('permission:reservations.view');
        
        // Reservations
        Route::get('/reservations/arrivals', [\App\Http\Controllers\Api\ReservationController::class, 'arrivals'])
            ->middleware('permission:reservations.view');
        Route::get('/reservations/departures', [\App\Http\Controllers\Api\ReservationController::class, 'departures'])
            ->middleware('permission:reservations.view');
        Route::apiResource('reservations', \App\Http\Controllers\Api\ReservationController::class)
            ->middleware('permission:reservations.view');
        Route::post('/reservations/{reservation}/cancel', [\App\Http\Controllers\Api\ReservationController::class, 'cancel'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/check-in', [\App\Http\Controllers\Api\ReservationController::class, 'checkIn'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/archive', [\App\Http\Controllers\Api\ReservationController::class, 'archive'])
            ->middleware('permission:reservations.edit');
        Route::post('/reservations/{reservation}/unarchive', [\App\Http\Controllers\Api\ReservationController::class, 'unarchive'])
            ->middleware('permission:reservations.edit');
    });
});