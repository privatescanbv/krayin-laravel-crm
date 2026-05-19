<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Reports\RevenueByEmployeeController;

Route::controller(RevenueByEmployeeController::class)
    ->prefix('reports/revenue-by-employee')
    ->group(function () {
        Route::get('', 'index')->name('admin.reports.revenue-by-employee.index');

        Route::get('data', 'data')->name('admin.reports.revenue-by-employee.data');

        Route::get('filter-options', 'filterOptions')->name('admin.reports.revenue-by-employee.filter-options');
    });
