<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Reports\RevenueByEmployeeController;
use Webkul\Admin\Http\Controllers\Reports\RevenueByMonthController;

Route::controller(RevenueByEmployeeController::class)
    ->prefix('reports/revenue-by-employee')
    ->group(function () {
        Route::get('', 'index')->name('admin.reports.revenue-by-employee.index');

        Route::get('data', 'data')->name('admin.reports.revenue-by-employee.data');

        Route::get('filter-options', 'filterOptions')->name('admin.reports.revenue-by-employee.filter-options');
    });

Route::controller(RevenueByMonthController::class)
    ->prefix('reports/revenue-by-month')
    ->group(function () {
        Route::get('', 'index')->name('admin.reports.revenue-by-month.index');
        Route::get('data', 'data')->name('admin.reports.revenue-by-month.data');
    });
