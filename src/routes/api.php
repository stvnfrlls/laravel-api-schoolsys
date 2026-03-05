<?php

use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// -------------------------------------------------------------------------
// Student
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    // Add student routes here
});

// -------------------------------------------------------------------------
// Faculty
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:faculty'])->group(function () {
    // Add faculty routes here
});

// -------------------------------------------------------------------------
// Sub-admin
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:sub-admin'])->group(function () {
    // Add sub-admin routes here
});

// -------------------------------------------------------------------------
// Admin only
// -------------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // Full user management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // Role CRUD
    Route::apiResource('roles', RoleController::class);

    // Assign / remove / sync roles on a user
    Route::post('/users/{user}/roles', [RoleController::class, 'assignToUser']);
    Route::put('/users/{user}/roles', [RoleController::class, 'syncUserRoles']);
    Route::delete('/users/{user}/roles/{role}', [RoleController::class, 'removeFromUser']);

});