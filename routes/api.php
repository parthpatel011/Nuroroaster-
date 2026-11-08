<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\LoginController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RosterController;
use App\Http\Controllers\Api\V1\ShiftDefinitionController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\WardController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

Route::middleware(['auth', 'tenant', 'throttle:120,1'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/me', MeController::class);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::post('/staff', [StaffController::class, 'store']);
    Route::patch('/staff/{employee}', [StaffController::class, 'update']);
    Route::get('/wards', [WardController::class, 'index']);
    Route::post('/wards', [WardController::class, 'store']);
    Route::get('/shift-definitions', [ShiftDefinitionController::class, 'index']);
    Route::post('/shift-definitions', [ShiftDefinitionController::class, 'store']);
    Route::get('/rosters', [RosterController::class, 'index']);
    Route::post('/rosters', [RosterController::class, 'store']);
    Route::get('/rosters/{roster}', [RosterController::class, 'show']);
    Route::post('/rosters/{roster}/assignments', [AssignmentController::class, 'store']);
    Route::delete('/rosters/{roster}/assignments/{assignment}', [AssignmentController::class, 'destroy']);
});
