<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Dashboard\OperationalDashboardController;

Route::controller(OperationalDashboardController::class)
    ->prefix('operational-dashboard')
    ->group(function () {
        Route::get('', 'index')->name('admin.operational-dashboard.index');

        Route::get('queues', 'getQueue')->name('admin.operational-dashboard.queue');
    });

