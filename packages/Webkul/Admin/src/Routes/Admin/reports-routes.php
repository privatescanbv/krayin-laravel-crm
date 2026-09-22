<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Reports\OrdersByInvestigationDateController;
use Webkul\Admin\Http\Controllers\Reports\RevenueByEmployeeController;
use Webkul\Admin\Http\Controllers\Reports\RevenueByMonthController;

/**
 * These three reports are being replaced by the embedded Metabase dashboards
 * (see metabase-dashboard-routes.php) and are marked "(wordt verwijderd)" in
 * the dashboard menu. `reports.legacy` is not exposed anywhere in the ACL
 * tree, so a custom role can never be granted it — only a "beheerder" role
 * (permission_type "all", see User::hasPermission()) can reach these.
 */
Route::middleware('bouncer.permission:reports.legacy')->group(function () {
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

    Route::controller(OrdersByInvestigationDateController::class)
        ->prefix('reports/orders-by-investigation-date')
        ->group(function () {
            Route::get('', 'index')->name('admin.reports.orders-by-investigation-date.index');
            Route::get('data', 'data')->name('admin.reports.orders-by-investigation-date.data');
        });
});
