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
Route::post('/verify-email', [\App\Http\Controllers\Api\VerificationController::class, 'verifyApi']);

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
            ->middleware(['permission:rooms.view'])
            ->except(['destroy']);
        Route::delete('/room-types/{roomType}', [\App\Http\Controllers\Api\RoomTypeController::class, 'destroy'])
            ->middleware('permission:rooms.edit');
        Route::get('/room-types/{roomType}/availability', [\App\Http\Controllers\Api\RoomTypeController::class, 'availability'])
            ->middleware('permission:rooms.view');
        
        // Rooms
        Route::get('/rooms/availability', [\App\Http\Controllers\Api\RoomController::class, 'availability'])
            ->middleware('permission:rooms.view');
        Route::get('/rooms/statistics', [\App\Http\Controllers\Api\RoomController::class, 'statistics'])
            ->middleware('permission:dashboard.view');
        Route::apiResource('rooms', \App\Http\Controllers\Api\RoomController::class)
            ->middleware(['permission:rooms.view'])
            ->except(['destroy']);
        Route::delete('/rooms/{room}', [\App\Http\Controllers\Api\RoomController::class, 'destroy'])
            ->middleware('permission:rooms.edit');
        
        // Room history and notes
        Route::get('/rooms/{room}/history', [\App\Http\Controllers\Api\RoomController::class, 'history'])
            ->middleware('permission:rooms.view');
        Route::get('/rooms/{room}/notes', [\App\Http\Controllers\Api\RoomController::class, 'getNotes'])
            ->middleware('permission:rooms.view');
        Route::post('/rooms/{room}/notes', [\App\Http\Controllers\Api\RoomController::class, 'addNote'])
            ->middleware('permission:rooms.edit');
        Route::delete('/rooms/{room}/notes/{note}', [\App\Http\Controllers\Api\RoomController::class, 'deleteNote'])
            ->middleware('permission:rooms.edit');
        
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
        
        // Hotel users management
        Route::get('/hotels/{hotel}/users', [\App\Http\Controllers\Api\HotelUserController::class, 'index']);
        Route::get('/hotels/{hotel}/users/me/permissions', [\App\Http\Controllers\Api\HotelUserController::class, 'currentUserPermissions']);
        Route::post('/hotels/{hotel}/users/invite', [\App\Http\Controllers\Api\HotelUserController::class, 'invite']);
        Route::post('/hotels/{hotel}/users/create-staff', [\App\Http\Controllers\Api\HotelUserController::class, 'createStaff']);
        Route::put('/hotels/{hotel}/users/{user}/role', [\App\Http\Controllers\Api\HotelUserController::class, 'updateRole']);
        Route::delete('/hotels/{hotel}/users/{user}', [\App\Http\Controllers\Api\HotelUserController::class, 'remove']);
        
        // Housekeeping staff management
        Route::apiResource('housekeeping-staff', \App\Http\Controllers\Api\HousekeepingStaffController::class);
        Route::get('/housekeeping-staff/{staff}/statistics', [\App\Http\Controllers\Api\HousekeepingStaffController::class, 'statistics']);
        
        // Room assignments
        Route::get('/assignments', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'index']);
        Route::post('/assignments/auto-assign', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'autoAssign']);
        Route::put('/assignments/{assignment}/reassign', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'reassign']);
        Route::post('/assignments/{assignment}/start', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'start']);
        Route::post('/assignments/{assignment}/complete', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'complete']);
        Route::post('/assignments/{assignment}/validate', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'validate']);
        Route::post('/assignments/{assignment}/report-issue', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'reportIssue']);
        Route::get('/assignments/my-assignments', [\App\Http\Controllers\Api\RoomAssignmentController::class, 'myAssignments']);
    });
});