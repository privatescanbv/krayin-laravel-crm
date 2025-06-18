<?php

use App\Http\Controllers\LeadNoteController;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Lead\ActivityController;
use Webkul\Admin\Http\Controllers\Settings\GroupController;
use Webkul\Lead\Http\Controllers\Api\LeadController;

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

// Route::middleware('auth:sanctum')->group(function () {
// Lead routes
Route::prefix('leads')->group(function () {
    Route::get('/', [LeadController::class, 'index']);
    Route::post('/', [LeadController::class, 'store']);
    Route::get('{id}', [LeadController::class, 'show']);
    Route::put('{id}', [LeadController::class, 'update']);
    Route::patch('{id}/stage', [LeadController::class, 'updateStage']);
    Route::delete('{id}', [LeadController::class, 'destroy']);
    // notes
    Route::post('{leadId}/notes', [LeadNoteController::class, 'store']);

    // Lead activities
    Route::post('{id}/activities', [ActivityController::class, 'store']);
    Route::get('{id}/activities', [ActivityController::class, 'index']);
});

// Existing routes

Route::get('groups/byDepartment/{departmentName}', [GroupController::class, 'findByDepartment']);
// });
