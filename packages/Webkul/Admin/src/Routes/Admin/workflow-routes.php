<?php

use App\Http\Controllers\Admin\SalesLeadController;
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
    Route::delete('workflow-leads/{id}', [SalesLeadController::class, 'delete'])->name('admin.workflow-leads.delete');

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
