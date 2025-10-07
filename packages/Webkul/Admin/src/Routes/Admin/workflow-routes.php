<?php

use App\Http\Controllers\Admin\SalesLeadController;
use App\Http\Controllers\Admin\SalesLeadEmailController;
use Illuminate\Support\Facades\File;

/**
 * Workflow routes.
 */
Route::group(['middleware' => ['user']], function () {
    Route::get('workflow-leads', [SalesLeadController::class, 'index'])->name('admin.workflow-leads.index');
    Route::get('workflow-leads/get', [SalesLeadController::class, 'get'])->name('admin.workflow-leads.get');
    Route::get('workflow-leads/create', [SalesLeadController::class, 'create'])->name('admin.workflow-leads.create');
    Route::post('workflow-leads/create', [SalesLeadController::class, 'store'])->name('admin.workflow-leads.store');
    Route::get('workflow-leads/edit/{id}', [SalesLeadController::class, 'edit'])->name('admin.workflow-leads.edit');
    Route::put('workflow-leads/edit/{id}', [SalesLeadController::class, 'update'])->name('admin.workflow-leads.update');
    Route::get('workflow-leads/view/{id}', [SalesLeadController::class, 'view'])->name('admin.workflow-leads.view');
    Route::put('workflow-leads/{id}/stage', [SalesLeadController::class, 'updateStage'])->name('admin.workflow-leads.stage.update');
    Route::delete('workflow-leads/{id}', [SalesLeadController::class, 'delete'])->name('admin.workflow-leads.delete');
    
    // Activity routes
    Route::get('workflow-leads/{id}/activities', [SalesLeadController::class, 'activities'])->name('admin.workflow-leads.activities.index');
    Route::post('workflow-leads/{id}/activities', [SalesLeadController::class, 'storeActivity'])->name('admin.workflow-leads.activities.store');
    
    // Email routes
    Route::controller(SalesLeadEmailController::class)->prefix('workflow-leads/{id}/emails')->group(function () {
        Route::post('', 'store')->name('admin.workflow-leads.emails.store');
        Route::delete('', 'detach')->name('admin.workflow-leads.emails.detach');
    });

    // Temporary debug route
    Route::get('workflow-leads/debug/{id}', [SalesLeadController::class, 'debug'])->name('admin.workflow-leads.debug');
});

/**
 * Documentation routes.
 */
Route::group(['middleware' => ['user']], function () {
//    Route::get('docs/{path?}', function ($path = 'index.html') {
    Route::get('docs/{path?}', function ($path = 'index.html') {
        $fullPath = base_path("docs/html/{$path}");

        if (! File::exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    })->where('path', '.*')->name('admin.docs.index');
});
