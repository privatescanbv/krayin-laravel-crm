<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Settings\OrderController;
use App\Http\Controllers\Admin\Settings\OrderRegelController;
use App\Http\Controllers\Admin\Planning\OrderItemPlanningController;
use App\Http\Controllers\Admin\Planning\ResourcePlanningMonitorController;

/**
 * Orders routes (top-level, not under settings).
 */
Route::controller(OrderController::class)->prefix('orders')->group(function () {
    Route::get('', 'index')->name('admin.orders.index');
    Route::get('create', 'create')->name('admin.orders.create');
    Route::post('create', 'store')->name('admin.orders.store');
    Route::get('edit/{id}', 'edit')->name('admin.orders.edit');
    Route::put('edit/{id}', 'update')->name('admin.orders.update');
    Route::delete('', 'destroy')->name('admin.orders.delete');
    Route::delete('{id}', 'destroy')->name('admin.orders.delete');
});

/**
 * Order regels routes (top-level, not under settings).
 */
Route::controller(OrderRegelController::class)->prefix('order-regels')->group(function () {
    Route::get('', 'index')->name('admin.order_regels.index');
    Route::get('create', 'create')->name('admin.order_regels.create');
    Route::post('create', 'store')->name('admin.order_regels.store');
    Route::get('edit/{id}', 'edit')->name('admin.order_regels.edit');
    Route::put('edit/{id}', 'update')->name('admin.order_regels.update');
    Route::delete('', 'destroy')->name('admin.order_regels.delete');
    Route::delete('{id}', 'destroy')->name('admin.order_regels.delete');
});

/**
 * Planning routes.
 */
Route::middleware(['user'])->controller(OrderItemPlanningController::class)->prefix('planning')->group(function () {
    Route::get('order-item/{orderItemId}', 'show')->name('admin.planning.order_item.show');
    Route::get('order-item/{orderItemId}/availability', 'availability')->name('admin.planning.order_item.availability');
    Route::post('order-item/{orderItemId}/book', 'book')->name('admin.planning.order_item.book');
});

/**
 * Planning Monitor routes.
 */
Route::middleware(['user'])->controller(ResourcePlanningMonitorController::class)->prefix('planning/monitor')->group(function () {
    Route::get('', 'index')->name('admin.planning.monitor.index');
    Route::get('availability', 'availability')->name('admin.planning.monitor.availability');
    Route::get('order/{orderId}', 'orderPlanning')->name('admin.planning.monitor.order');
    Route::get('order/{orderId}/availability', 'orderAvailability')->name('admin.planning.monitor.order.availability');
    Route::post('order-item/{orderItemId}/book', 'bookOrderItem')->name('admin.planning.monitor.order_item.book');
});


