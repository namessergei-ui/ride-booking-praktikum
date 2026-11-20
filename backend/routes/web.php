<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'Ride Booking API', 'version' => 'v1']));
