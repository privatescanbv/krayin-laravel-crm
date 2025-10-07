<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Configuration\ConfigurationController;

Route::controller(ConfigurationController::class)->prefix('configuration')->group(function () {
    Route::get('search', 'search')->name('admin.configuration.search');

    // Redirect old import runs URL to new location
    Route::get('report/import', function () {
        return redirect()->route('admin.settings.import-runs.index');
    });

    Route::prefix('{slug?}/{slug2?}')->group(function () {
        Route::get('', 'index')->name('admin.configuration.index');

        Route::post('', 'store')->name('admin.configuration.store');

        Route::get('{path}', 'download')->name('admin.configuration.download');
    });
});
