<?php

use App\Http\Controllers\Admin\Settings\PartnerProductController;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Settings\PartnerProducts\ActivityController as PartnerProductActivityController;

/**
 * Settings routes.
 */
Route::prefix('partner-products')->group(function () {
    /**
     * Partner Products routes.
     */
    Route::controller(PartnerProductController::class)->group(function () {
        Route::get('', 'index')->name('admin.partner_products.index');
        Route::get('create', 'create')->name('admin.partner_products.create');
        Route::post('create', 'store')->name('admin.partner_products.store');
        Route::get('view/{id}', 'view')->name('admin.partner_products.view');
        Route::get('edit/{id}', 'edit')->name('admin.partner_products.edit');
        Route::put('edit/{id}', 'update')->name('admin.partner_products.update');
        Route::delete('', 'destroy')->name('admin.partner_products.delete');
        Route::delete('{id}', 'destroy')->name('admin.partner_products.delete');
        Route::get('search', 'search')->name('admin.partner_products.search');
        Route::get('template-products', 'getTemplateProducts')->name('admin.partner_products.template_products');
        Route::get('template-product/{id}', 'getTemplateProduct')->name('admin.partner_products.template_product');
        Route::get('template-products', 'getTemplateProducts')->name('admin.partner_products.template_products');
        Route::get('template-product/{id}', 'getTemplateProduct')->name('admin.partner_products.template_product');

        Route::controller(PartnerProductActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.partner_products.activities.index');
        });
    });
});
