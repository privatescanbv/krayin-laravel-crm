<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Products\ActivityController;
use Webkul\Admin\Http\Controllers\Products\ProductController;
use Webkul\Admin\Http\Controllers\Products\ProductGroupController;
use Webkul\Admin\Http\Controllers\Products\TagController;

Route::group(['middleware' => ['user']], function () {
    Route::controller(ProductController::class)->prefix('products')->group(function () {
        Route::get('', 'index')->name('admin.products.index');

        Route::get('create', 'create')->name('admin.products.create');

        Route::post('create', 'store')->name('admin.products.store');

        Route::get('view/{id}', 'view')->name('admin.products.view');

        Route::get('edit/{id}', 'edit')->name('admin.products.edit');

        Route::put('edit/{id}', 'update')->name('admin.products.update');

        Route::get('search', 'search')->name('admin.products.search');

        // Route disabled: warehouses view is no longer supported
        // Route::get('{id}/warehouses', 'warehouses')->name('admin.products.warehouses');

        Route::post('{id}/inventories/{warehouseId?}', 'storeInventories')->name('admin.products.inventories.store');

        Route::delete('{id}', 'destroy')->name('admin.products.delete');

        Route::post('mass-destroy', 'massDestroy')->name('admin.products.mass_delete');

        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.products.activities.index');
        });

        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.products.tags.attach');

            Route::delete('', 'detach')->name('admin.products.tags.detach');
        });
    });

    Route::controller(ProductGroupController::class)->prefix('productgroups')->group(function () {
        Route::get('/', 'index')->name('admin.productgroups.index');
        Route::get('create', 'create')->name('admin.productgroups.create');
        Route::post('create', 'store')->name('admin.productgroups.store');
        Route::get('edit/{id}', 'edit')->name('admin.productgroups.edit');
        Route::put('edit/{id}', 'update')->name('admin.productgroups.update');
        Route::get('search', 'search')->name('admin.productgroups.search');
        Route::delete('{id}', 'destroy')->name('admin.productgroups.delete');
    });
});
