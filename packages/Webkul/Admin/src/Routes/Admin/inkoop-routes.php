<?php

use App\Http\Controllers\Admin\Inkoop\InkoopController;
use App\Http\Controllers\Admin\Inkoop\InkoopStep0Controller;
use App\Http\Controllers\Admin\Inkoop\InkoopStep1Controller;
use App\Http\Controllers\Admin\Inkoop\InkoopStep2Controller;
use App\Http\Controllers\Admin\Inkoop\InkoopStep3Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('inkoop')->group(function () {
    Route::get('clinics/{clinic}/upload', [InkoopController::class, 'showUpload'])
        ->name('admin.inkoop.clinics.upload');
    Route::post('clinics/{clinic}/upload', [InkoopController::class, 'store'])
        ->name('admin.inkoop.clinics.upload.store');

    Route::get('{invoice}/step0', [InkoopStep0Controller::class, 'show'])
        ->name('admin.inkoop.step0');
    Route::put('{invoice}/reference-date', [InkoopStep0Controller::class, 'update'])
        ->name('admin.inkoop.update-reference-date');

    Route::get('{invoice}/step1', [InkoopStep1Controller::class, 'handleStep'])
        ->name('admin.inkoop.step1');
    Route::put('{invoice}/save-crm-ids', [InkoopStep1Controller::class, 'saveAllCrmIds'])
        ->name('admin.inkoop.save-crm-ids');
    Route::put('{invoice}/persons/{person}/reset-crm-id', [InkoopStep1Controller::class, 'resetCrmId'])
        ->name('admin.inkoop.reset-person-crm-id');

    Route::get('{invoice}/step2', [InkoopStep2Controller::class, 'handleStep'])
        ->name('admin.inkoop.step2');
    Route::put('{invoice}/save-product-crm-ids', [InkoopStep2Controller::class, 'saveAllCrmIds'])
        ->name('admin.inkoop.save-product-crm-ids');
    Route::put('{invoice}/items/{item}/reset-crm-id', [InkoopStep2Controller::class, 'resetCrmId'])
        ->name('admin.inkoop.reset-crm-id');

    Route::get('{invoice}/step3', [InkoopStep3Controller::class, 'handleStep'])
        ->name('admin.inkoop.step3');
    Route::put('{invoice}/mark-as-processed', [InkoopStep3Controller::class, 'markAsProcessed'])
        ->name('admin.inkoop.mark-as-processed');

    Route::delete('{invoice}', [InkoopController::class, 'destroy'])
        ->name('admin.inkoop.delete');
});
