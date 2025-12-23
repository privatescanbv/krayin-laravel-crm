<?php

use App\Http\Controllers\Api\PatientMessageController;
use Illuminate\Support\Facades\Route;

Route::controller(PatientMessageController::class)->prefix('messages')->group(function () {
    Route::post('create', 'store')->name('admin.messages.store');

});

