<?php

use App\Http\Controllers\Admin\SalesLeadController;
use App\Http\Controllers\Admin\SalesLeadEmailController;
use Illuminate\Support\Facades\File;

/**
 * Sales Lead routes.
 */
Route::group(['middleware' => ['user']], function () {
    Route::get('sales-leads', [SalesLeadController::class, 'index'])->name('admin.sales-leads.index');
    Route::get('sales-leads/get', [SalesLeadController::class, 'get'])->name('admin.sales-leads.get');
    Route::get('sales-leads/create', [SalesLeadController::class, 'create'])->name('admin.sales-leads.create');
    Route::post('sales-leads/create', [SalesLeadController::class, 'store'])->name('admin.sales-leads.store');
    Route::get('sales-leads/edit/{id}', [SalesLeadController::class, 'edit'])->name('admin.sales-leads.edit');
    Route::put('sales-leads/edit/{id}', [SalesLeadController::class, 'update'])->name('admin.sales-leads.update');
    Route::get('sales-leads/view/{id}', [SalesLeadController::class, 'view'])->name('admin.sales-leads.view');
    Route::put('sales-leads/{id}/stage', [SalesLeadController::class, 'updateStage'])->name('admin.sales-leads.stage.update');
    Route::put('sales-leads/{id}/lost', [SalesLeadController::class, 'lost'])->name('admin.sales-leads.lost');
    Route::delete('sales-leads/{id}', [SalesLeadController::class, 'delete'])->name('admin.sales-leads.delete');
    Route::get('sales-leads/search', [SalesLeadController::class, 'search'])->name('admin.sales-leads.search');
    
    // Activity routes
    Route::get('sales-leads/{id}/activities', [SalesLeadController::class, 'activities'])->name('admin.sales-leads.activities.index');
    Route::post('sales-leads/{id}/activities', [SalesLeadController::class, 'storeActivity'])->name('admin.sales-leads.activities.store');
    
    // Email routes
    Route::controller(SalesLeadEmailController::class)->prefix('sales-leads/{id}/emails')->group(function () {
        Route::post('', 'store')->name('admin.sales-leads.emails.store');
        Route::delete('', 'detach')->name('admin.sales-leads.emails.detach');
    });

    // Temporary debug route
    Route::get('sales-leads/debug/{id}', [SalesLeadController::class, 'debug'])->name('admin.sales-leads.debug');
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
