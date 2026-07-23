<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Lead\ActivityController;
use Webkul\Admin\Http\Controllers\Lead\DuplicateController;
use Webkul\Admin\Http\Controllers\Lead\EmailController;
use Webkul\Admin\Http\Controllers\Lead\LeadController;
use Webkul\Admin\Http\Controllers\Lead\TagController;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use App\Http\Controllers\Admin\AnamnesisController;
use App\Http\Controllers\Admin\LeadAiSummaryController;

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

    Route::get('{leadId}/attach-person', 'attachPersonPage')->name('admin.leads.attach_person');

    Route::post('{leadId}/attach-person', 'attachPerson')->name('admin.leads.attach_person.store');

    Route::get('sync-lead-to-person/{leadId}/{personId}', [PersonController::class, 'syncLeadToPerson'])->name('admin.leads.sync-lead-to-person');

    Route::post('sync-lead-to-person/{leadId}/{personId}', [PersonController::class, 'syncLeadToPersonUpdate'])->name('admin.leads.sync-lead-to-person-update');

    Route::get('sync-anamnesis/{personId}', [AnamnesisController::class, 'syncLatestWithOlder'])->name('admin.leads.sync-anamnesis-to-older-update');
    Route::post('sync-anamnesis/{personId}', [AnamnesisController::class, 'storeSyncLatestWithOlder'])->name('admin.leads.sync-anamnesis-update');

    Route::get('kanban/look-up', [LeadController::class, 'kanbanLookup'])->name('admin.leads.kanban.look_up');

    Route::get('{id}/default-group', [ActivityController::class, 'getDefaultGroup'])->name('admin.leads.default-group');

    Route::get('{id}/diagnosis-form/download', 'downloadDiagnosisForm')->name('admin.leads.diagnosis-form.download');

    Route::controller(LeadAiSummaryController::class)->prefix('{id}/ai-summary')->group(function () {
        Route::get('', 'show')->name('admin.leads.ai-summary.show');
        Route::post('generate', 'generate')->name('admin.leads.ai-summary.generate');
        Route::post('feedback', 'storeFeedback')->name('admin.leads.ai-feedback.store');
        Route::put('feedback/{feedback}', 'updateFeedback')->name('admin.leads.ai-feedback.update');
        Route::delete('feedback/{feedback}', 'destroyFeedback')->name('admin.leads.ai-feedback.destroy');
    });

    Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
        Route::get('', 'index')->name('admin.leads.activities.index');
        Route::get('open/count', 'countOpen')->name('admin.leads.activities.open.count');
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

        Route::post('false-positive', 'markFalsePositive')->name('admin.leads.duplicates.false_positive');

        Route::get('debug', 'debug')->name('admin.leads.duplicates.debug');
    });
});

Route::prefix('anamnesis')->group(function () {
    Route::post('override', [AnamnesisController::class, 'override'])->name('admin.anamnesis.override');
    Route::delete('revert-override', [AnamnesisController::class, 'revertOverride'])->name('admin.anamnesis.revert-override');
    Route::get('edit/{id}', [AnamnesisController::class, 'edit'])->name('admin.anamnesis.edit');
    Route::put('edit/{id}', [AnamnesisController::class, 'update'])->name('admin.anamnesis.update');
    Route::post('create-and-attach-gvl-form', [AnamnesisController::class, 'createAndAttachGvlForm'])->name('admin.anamnesis.create-and-attach-gvl-form');
    Route::post('{id}/gvl-form', [AnamnesisController::class, 'attachGvlForm'])->name('admin.anamnesis.gvl-form.attach');
    Route::delete('{id}/gvl-form/{gvlFormRecordId}', [AnamnesisController::class, 'detachGvlForm'])->name('admin.anamnesis.gvl-form.detach');
    Route::get('{id}/gvl-form/{gvlFormRecordId}/status', [AnamnesisController::class, 'getGvlFormStatus'])->name('admin.anamnesis.gvl-form.status');
    Route::get('{id}/gvl-form/latest-status', [AnamnesisController::class, 'getLatestGvlFormStatus'])->name('admin.anamnesis.gvl-form.latest-status');
});
