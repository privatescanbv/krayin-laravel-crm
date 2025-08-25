<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Activity\ActivityController;
use Webkul\Admin\Http\Controllers\Activity\ActivityAssignmentController;

Route::controller(ActivityController::class)->prefix('activities')->group(function () {
    Route::get('', 'index')->name('admin.activities.index');

    Route::get('get', 'get')->name('admin.activities.get');
    
    Route::get('views', 'getViews')->name('admin.activities.views');

    Route::post('create', 'store')->name('admin.activities.store');

    Route::get('edit/{id}', 'edit')->name('admin.activities.edit');

    Route::put('edit/{id}', 'update')->name('admin.activities.update');

    Route::get('download/{id}', 'download')->name('admin.activities.file_download');

    Route::delete('{id}', 'destroy')->name('admin.activities.delete');

    Route::post('mass-update', 'massUpdate')->name('admin.activities.mass_update');

    Route::post('mass-destroy', 'massDestroy')->name('admin.activities.mass_delete');
});

Route::controller(ActivityAssignmentController::class)->prefix('activities')->group(function () {
    Route::post('{id}/assign', 'assign')->name('admin.activities.assign');
    
    Route::post('{id}/takeover', 'takeover')
        ->name('admin.activities.takeover')
        ->middleware('bouncer.permission:activities.takeover');
        
    Route::post('{id}/unassign', 'unassign')->name('admin.activities.unassign');
});
