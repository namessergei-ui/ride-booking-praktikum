<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::pattern('trip', '[1-9][0-9]{0,17}');
Route::pattern('booking', '[1-9][0-9]{0,17}');

Route::prefix('v1')->scopeBindings()->group(function (): void {
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });

    Route::get('cities', [CityController::class, 'index']);
    Route::get('trips', [TripController::class, 'index']);
    Route::get('trips/{trip}', [TripController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::put('me/driver', [ProfileController::class, 'saveDriver']);

        Route::post('trips', [TripController::class, 'store']);
        Route::get('trips/{trip}/participants', [TripController::class, 'participants']);

        Route::get('bookings', [BookingController::class, 'mine']);
        Route::get('trips/{trip}/bookings', [BookingController::class, 'index']);
        Route::post('trips/{trip}/bookings', [BookingController::class, 'store']);
        Route::get('trips/{trip}/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('trips/{trip}/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::post('trips/{trip}/bookings/{booking}/reject', [BookingController::class, 'reject']);
        Route::post('trips/{trip}/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::put('trips/{trip}/bookings/{booking}/rating', [BookingController::class, 'rate']);

        Route::get('trips/{trip}/messages', [ChatController::class, 'index']);
        Route::post('trips/{trip}/messages', [ChatController::class, 'store'])->middleware('throttle:30,1');
    });
});
