<?php

use App\Http\Controllers\VehicleTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/vehicle-types', [VehicleTypeController::class, 'index']);
