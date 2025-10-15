<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Lead\ActivityController;
use Webkul\Admin\Http\Controllers\Lead\DuplicateController;
use Webkul\Admin\Http\Controllers\Lead\EmailController;
use Webkul\Admin\Http\Controllers\Lead\LeadController;
use Webkul\Admin\Http\Controllers\Lead\TagController;
use App\Http\Controllers\Admin\AnamnesisController;

Route::controller(LeadController::class)->prefix('leads')->group(function () {
    Route::get('', 'index')->name('admin.leads.index');

    Route::get('create', 'create')->name('admin.leads.create');

    Route::post('create', 'store')->name('admin.leads.store');

    Route::post('create-by-ai', 'createByAI')->name('admin.leads.create_by_ai');

    Route::get('view/{id}', 'view')->name('admin.leads.view');

    Route::get('edit/{id}', 'edit')->name('admin.leads.edit');

    Route::put('edit/{id}', 'update')->name('admin.leads.update');

    Route::put('stage/edit/{id}', 'updateStage')->name('admin.leads.stage.update');

    Route::put('{id}/lost', 'markAsLost')->name('admin.leads.lost');

    Route::get('search', 'search')->name('admin.leads.search');
    Route::get('open-by-person/{person}', 'openByPerson')->name('admin.leads.open_by_person');

    Route::delete('{id}', 'destroy')->name('admin.leads.delete');

    Route::post('mass-update', 'massUpdate')->name('admin.leads.mass_update');

    Route::post('mass-destroy', 'massDestroy')->name('admin.leads.mass_delete');

    Route::get('get/{pipeline_id?}', 'get')->name('admin.leads.get');

    Route::delete('{leadId}/detach-person/{personId}', 'detachPerson')->name('admin.leads.detach_person');

    Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.kanban.look_up');

    Route::get('{id}/default-group', [ActivityController::class, 'getDefaultGroup'])->name('admin.leads.default-group');

    Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
        Route::get('', 'index')->name('admin.leads.activities.index');
        Route::post('', 'store')->name('admin.leads.activities.store');
    });

    Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
        Route::post('', 'attach')->name('admin.leads.tags.attach');

        Route::delete('', 'detach')->name('admin.leads.tags.detach');
    });

    Route::controller(EmailController::class)->prefix('{id}/emails')->group(function () {
        Route::post('', 'store')->name('admin.leads.emails.store');

        Route::delete('', 'detach')->name('admin.leads.emails.detach');
    });

    // Duplicate management routes
    Route::controller(DuplicateController::class)->prefix('{id}/duplicates')->group(function () {
        Route::get('', 'index')->name('admin.leads.duplicates.index');

        Route::get('check', 'checkDuplicates')->name('admin.leads.duplicates.check');

        Route::get('get', 'getDuplicates')->name('admin.leads.duplicates.get');

        Route::post('merge', 'merge')->name('admin.leads.duplicates.merge');

        Route::get('debug', 'debug')->name('admin.leads.duplicates.debug');
    });
});

Route::controller(AnamnesisController::class)->prefix('anamnesis')->group(function () {
    Route::get('edit/{id}', 'edit')->name('admin.anamnesis.edit');
    Route::put('edit/{id}', 'update')->name('admin.anamnesis.update');
});
