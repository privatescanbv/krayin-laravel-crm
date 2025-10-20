<?php

use App\Http\Controllers\Api\SalesLeadController;
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

// All API routes are protected with API key authentication
Route::middleware('api.key')->group(function () {
    // Lead routes
    Route::prefix('leads')->group(function () {
        // Removed index route - too heavy, use /admin/leads/get for kanban instead
        Route::post('/', [LeadController::class, 'store']);
        Route::get('{id}', [LeadController::class, 'show']);
        Route::put('{id}', [LeadController::class, 'update']);
        Route::patch('{id}/stage', [LeadController::class, 'updateStage']);
        Route::patch('{id}/next_stage', [LeadController::class, 'nextStage']);
        Route::delete('{id}', [LeadController::class, 'destroy']);
        // notes
        Route::post('{leadId}/notes', [LeadNoteController::class, 'store']);

        // Lead activities
        Route::post('{id}/activities', [ActivityController::class, 'store'])->name('admin.leads.activities.store');
        Route::get('{id}/activities', [ActivityController::class, 'index']);
    });

    // Existing routes
    Route::get('groups/byDepartment/{departmentName}', [GroupController::class, 'findByDepartment']);

    // Workflow Leads API
    Route::prefix('sales-leads')->group(function () {
        Route::post('/', [SalesLeadController::class, 'store']);
        // Sales lead activities
        Route::get('{id}/activities', [SalesLeadController::class, 'activities']);
        Route::post('{id}/activities', [SalesLeadController::class, 'storeActivity']);
    });

    // Backward-compatible singular prefix for n8n (sales-lead)
    Route::prefix('sales-leads')->group(function () {
        Route::get('{id}/activities', [SalesLeadController::class, 'activities']);
        Route::post('{id}/activities', [SalesLeadController::class, 'storeActivity']);
    });
});
