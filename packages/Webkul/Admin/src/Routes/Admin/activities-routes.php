<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Activity\ActivityController;
use Webkul\Admin\Http\Controllers\Activity\ActivityAssignmentController;
use Webkul\Admin\Http\Controllers\Activity\CallStatusController;

Route::controller(ActivityController::class)->prefix('activities')->group(function () {
    Route::get('', 'index')->name('admin.activities.index');

    Route::get('get', 'get')->name('admin.activities.get');

    Route::get('views', 'getViews')->name('admin.activities.views');

    Route::get('view/{id}', 'view')->name('admin.activities.view');

    Route::post('create', 'store')->name('admin.activities.store');

    Route::get('edit/{id}', 'edit')->name('admin.activities.edit');

    Route::put('edit/{id}', 'update')->name('admin.activities.update');

    Route::get('download/{id}', 'download')->name('admin.activities.file_download');

    Route::delete('{id}', 'destroy')->name('admin.activities.delete');

    Route::post('mass-update', 'massUpdate')->name('admin.activities.mass_update');

    Route::post('mass-destroy', 'massDestroy')->name('admin.activities.mass_delete');

    // Open activities by lead (for email linking UI)
    Route::get('by-lead/{leadId}/open', function($leadId) {
        return app(ActivityController::class)->openByLead($leadId);
    })->name('admin.activities.by_lead_open');

    // Fetch persons linked to an entity (for file upload portal selector)
    Route::get('persons-for-entity', 'personsForEntity')->name('admin.activities.persons-for-entity');
});

Route::controller(ActivityAssignmentController::class)->prefix('activities')->group(function () {
    Route::post('{id}/assign', 'assign')->name('admin.activities.assign');

    Route::post('{id}/takeover', 'takeover')
        ->name('admin.activities.takeover')
        ->middleware('bouncer.permission:activities.takeover');

    Route::post('{id}/unassign', 'unassign')->name('admin.activities.unassign');
});

Route::controller(CallStatusController::class)->prefix('activities/{activityId}/call-statuses')->group(function () {
    Route::get('', 'index')->name('admin.activities.call-statuses.index');
    Route::post('', 'store')->name('admin.activities.call-statuses.store');
});
