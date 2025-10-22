<?php

use App\Http\Controllers\Admin\ClinicProducts\ClinicProductController;
use Illuminate\Support\Facades\Route;

/**
 * Clinic Products routes.
 */
Route::prefix('clinic-products')->group(function () {
    Route::controller(ClinicProductController::class)->group(function () {
        Route::get('{clinic_id}', 'index')->name('admin.clinic_products.index');
        Route::delete('{clinic_id}/{partner_product_id}', 'destroy')->name('admin.clinic_products.delete');
    });
});
